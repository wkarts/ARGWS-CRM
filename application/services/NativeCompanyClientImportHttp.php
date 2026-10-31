<?php

declare(strict_types=1);

require_once __DIR__ . '/CompanyClientImports.php';

/**
 * Binds the private customer import/export writer to the original
 * admin/clients/import route and view.
 */
final class NativeCompanyClientImportHttp
{
    public function __construct(private object $ci)
    {
    }

    public function handle(): void
    {
        if (!CompanyRequestScope::nativeFor($this->ci)) {
            show_error('Contexto de empresa indisponível.', 503);
            return;
        }

        $scope  = CompanyRequestScope::context();
        $issuer = $scope?->issuerId();
        $actor  = $scope?->staffId();
        if (!is_int($issuer) || !is_int($actor) || $issuer < 1 || $actor < 1) {
            show_error('Selecione uma empresa autorizada.', 403);
            return;
        }

        $service = new CompanyClientImports($this->ci->db, db_prefix());
        $acl     = new IssuerResourceAccess($this->ci->db, db_prefix());
        $this->ci->output->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_header('Referrer-Policy: no-referrer');

        $error      = null;
        $batch      = null;
        $batches    = [];
        $clientPage = ['items' => [], 'next' => null, 'after' => 0];
        $canImport  = $acl->can($issuer, $actor, 'imports', 'manage')
            && $acl->can($issuer, $actor, 'customers', 'create');

        try {
            $verb = $this->ci->input->method(true);
            if ($verb === 'POST') {
                CompanyRequestScope::assertNativeMutation($this->ci);
                $action = $this->ci->input->post('action', false);
                $key    = $this->ci->input->post('request_key', false);
                if (!is_string($key)) {
                    throw new DomainException('company_client_import_invalid_input');
                }

                if ($action === 'preview') {
                    $format = $this->ci->input->post('format', false);
                    $file   = $_FILES['source_file'] ?? null;
                    if (!is_string($format) || !in_array($format, ['csv', 'json'], true)
                        || !is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK
                        || !is_int($file['size'] ?? null) || $file['size'] > 262144
                        || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)
                        || !is_uploaded_file($file['tmp_name'])
                        || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== $format
                        || preg_match('/[\x00-\x1f]/', $file['name'])
                        || str_contains($file['name'], '/') || str_contains($file['name'], chr(92))) {
                        throw new DomainException('company_client_import_invalid_file');
                    }
                    $bytes = file_get_contents($file['tmp_name'], false, null, 0, 262145);
                    if (!is_string($bytes)) {
                        throw new DomainException('company_client_import_invalid_file');
                    }
                    $id = $service->preview($issuer, $actor, $format, $bytes, $key);
                } elseif ($action === 'commit') {
                    $id       = $this->ci->input->post('batch_id', false);
                    $revision = $this->ci->input->post('revision', false);
                    if (!is_string($id) || !is_string($revision)) {
                        throw new DomainException('company_client_import_invalid_input');
                    }
                    $service->commit($issuer, $actor, $id, $revision, $key);
                } else {
                    throw new DomainException('company_client_import_invalid_input');
                }

                CompanyRequestScope::invalidateCaches($this->ci);
                redirect(CompanyRequestScope::adminUrl($this->ci, 'clients/import?batch=' . $id), 'location', 303);
                return;
            }

            if (!in_array($verb, ['GET', 'HEAD'], true)) {
                show_error('Método não permitido.', 405);
                return;
            }

            $export = $this->ci->input->get('export', false);
            if ($export !== null && $export !== 'clients') {
                throw new DomainException('company_client_import_invalid_input');
            }
            if ($export === 'clients') {
                $result = $service->export($issuer, $actor, $this->ci->input->get('after', false) ?? 0);
                $this->ci->output->set_header('X-Content-Type-Options: nosniff')
                    ->set_header('Content-Disposition: attachment; filename="clientes-empresa-' . $issuer . '.csv"')
                    ->set_header('X-Next-After: ' . ($result['next'] ?? ''))
                    ->set_content_type('text/csv', 'UTF-8')
                    ->set_output($verb === 'HEAD' ? '' : $result['csv']);
                return;
            }

            if (!$acl->can($issuer, $actor, 'customers', 'view')) {
                throw new DomainException('company_client_import_access_denied');
            }
            $id = $this->ci->input->get('batch', false);
            if ($id !== null) {
                if (!is_string($id)) {
                    throw new DomainException('company_client_import_invalid_input');
                }
                $batch = $service->get($issuer, $actor, $id);
            }
            $clientPage = $service->clientPage($issuer, $actor, $this->ci->input->get('after', false) ?? 0);
            if ($canImport) {
                $batches = $service->page($issuer, $actor);
            }
        } catch (DomainException $exception) {
            $reason = $exception->getMessage();
            if (in_array($reason, ['company_client_import_access_denied', 'company_client_access_denied'], true)) {
                show_error('Lote indisponível ou ação não autorizada nesta empresa.', 403);
                return;
            }
            if ($reason === 'native_company_csrf_invalid') {
                show_error('Recarregue o formulário antes de salvar.', 403);
                return;
            }
            $this->ci->output->set_status_header(in_array($reason, [
                'company_client_import_stale',
                'company_client_import_request_conflict',
                'company_client_identity_conflict',
            ], true) ? 409 : 422);
            $error = match ($reason) {
                'company_client_import_stale' => 'O preview mudou. Gere um novo preview antes de confirmar.',
                'company_client_import_request_conflict' => 'Esta solicitação já foi utilizada. Reabra o lote e confira o resultado.',
                'company_client_identity_conflict' => 'Um CPF/CNPJ conflita com uma identidade existente ou se repete no lote. Nenhum cliente do lote foi criado; revise os dados.',
                'company_client_import_expired' => 'O preview expirou. Envie novamente o arquivo.',
                'company_client_import_preview_limit' => 'Existem dez previews válidos aguardando confirmação. Revise-os ou aguarde a expiração.',
                'issuer_invalid_tax_id' => 'CPF/CNPJ inválido. Confira as informações do arquivo.',
                default => 'Arquivo inválido. Use somente company e vat, até 100 linhas/256 KiB em UTF-8. Nenhum cliente desta tentativa foi criado.',
            };
        } catch (Throwable $exception) {
            log_message('error', 'Native client import/export failed; no file contents or private identity data logged.');
            $this->ci->output->set_status_header(503);
            $error = 'Não foi possível confirmar a operação. Reabra o lote antes de repetir. A tentativa não foi parcialmente aplicada.';
        }

        $this->ci->load->view('admin/clients/import', [
            'title'                    => _l('import'),
            'native_customers_import'  => true,
            'issuer'                   => $issuer,
            'batch'                    => $batch,
            'batches'                  => $batches,
            'client_page'              => $clientPage,
            'can_import'               => $canImport,
            'error'                    => $error,
            'company_clients_csrf'     => CompanyRequestScope::nativeCsrfToken($this->ci),
            'company_context_token'    => CompanyRequestScope::token(),
        ]);
    }
}
