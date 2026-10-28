<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Reviewed independent cost only; native CI CSRF and sealed company context remain mandatory. */
final class NativeCompanyExpenseCopiesController
{
    private static function sourceId($value): int
    {
        $reference = NativeCompanyResourceId::decode('expense', $value);
        if ($reference['source_kind'] !== 'company_expense') {
            throw new DomainException('company_expense_copy_denied');
        }

        return $reference['source_id'];
    }

    public static function handle($ci): void
    {
        if (!CompanyRequestScope::sealedFor($ci)) {
            show_error('Contexto indisponível.', 503);
            return;
        }

        require_once APPPATH.'services/CompanyExpenseCopies.php';
        $context = CompanyRequestScope::context();
        $issuer = $context->issuerId();
        $actor = $context->staffId();
        $acl = new IssuerResourceAccess($ci->db, db_prefix());
        foreach (['view', 'create'] as $capability) {
            if (!$acl->can($issuer, $actor, 'expenses', $capability)) {
                show_error('Sem permissão para copiar despesas nesta empresa.', 403);
                return;
            }
        }

        $ci->output->set_header('Cache-Control: no-store');
        $service = new CompanyExpenseCopies($ci->db, db_prefix());
        $csrf = $ci->session->userdata('native_expense_copy_csrf');
        if (!is_string($csrf) || !preg_match('/^[a-f0-9]{64}$/D', $csrf)) {
            $csrf = bin2hex(random_bytes(32));
            $ci->session->set_userdata('native_expense_copy_csrf', $csrf);
        }

        $preview = $review = $receipt = $error = $attempt = null;
        $source = $values = null;
        try {
            $verb = $ci->input->method(true);
            if ($verb === 'POST') {
                $token = $ci->input->post('native_expense_copy_csrf', false);
                if (!is_string($token) || !hash_equals($csrf, $token)) {
                    show_error('Reabra a revisão antes de copiar.', 403);
                    return;
                }
                $action = $ci->input->post('action', false);
                if (!in_array($action, ['review_expense_copy', 'copy_reviewed_expense'], true)) {
                    show_404();
                    return;
                }
                $sourceId = self::sourceId($ci->input->post('source_expense_id', false));
                $source = NativeCompanyResourceId::encode('company_expense', $sourceId);
                if ($action === 'copy_reviewed_expense') {
                    $data = $ci->input->post('review', false);
                    $key = $ci->input->post('request_key', false);
                    if (!is_array($data) || !is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) {
                        throw new DomainException('company_expense_copy_invalid');
                    }
                    $attempt = $key;
                    $result = $service->create($issuer, $actor, $sourceId, $data, $key);
                    CompanyRequestScope::invalidateCaches($ci);
                    redirect(CompanyRequestScope::adminUrl($ci, 'expenses/copy?source=company&receipt='.$result['request_key']), 'location', 303);
                    return;
                }
                $values = $ci->input->post('expense', false);
                $revision = $ci->input->post('source_revision', false);
                if (!is_array($values) || !is_string($revision)) {
                    throw new DomainException('company_expense_copy_invalid');
                }
                $preview = $service->preview($issuer, $actor, $sourceId);
                $review = $service->review($issuer, $actor, $sourceId, $revision, $values);
            } elseif (in_array($verb, ['GET', 'HEAD'], true)) {
                $id = $ci->input->get('expense', false);
                $key = $ci->input->get('receipt', false);
                if (($id === null) === ($key === null)) {
                    show_404();
                    return;
                }
                if ($id !== null) {
                    if (!is_scalar($id) || $key !== null) {
                        show_404();
                        return;
                    }
                    $sourceId = self::sourceId($id);
                    $source = NativeCompanyResourceId::encode('company_expense', $sourceId);
                    $preview = $service->preview($issuer, $actor, $sourceId);
                    $values = $preview['input'];
                    $values['date'] = date('Y-m-d');
                } else {
                    if (!is_string($key)) {
                        show_404();
                        return;
                    }
                    $receipt = $service->receipt($issuer, $actor, $key);
                }
            } else {
                show_error('Método não permitido.', 405);
                return;
            }
        } catch (DomainException $errorException) {
            if (in_array($errorException->getMessage(), ['company_expense_copy_denied', 'company_expense_denied', 'company_expense_reference_denied', 'issuer_access_denied', 'native_resource_id_invalid', 'native_resource_namespace_mismatch'], true)) {
                show_error('Cópia indisponível para esta empresa, origem ou referências atuais.', 403);
                return;
            }
            $ci->output->set_status_header(str_contains($errorException->getMessage(), 'stale') || str_contains($errorException->getMessage(), 'conflict') ? 409 : 422);
            $error = 'A revisão mudou ou contém valores inválidos. Reabra a origem e confirme os dados atuais.';
        } catch (Throwable $unexpected) {
            log_message('error', 'Reviewed expense copy unconfirmed; no source contents logged.');
            $ci->output->set_status_header(503);
            $error = 'Não foi possível confirmar esta tentativa. Consulte o recibo desta chave antes de iniciar outra cópia.';
        }

        $ci->load->view('admin/expenses/company_copy', compact('issuer', 'source', 'preview', 'review', 'receipt', 'error', 'attempt', 'values') + [
            'request_key' => bin2hex(random_bytes(32)),
            'native_expense_copy_csrf' => $csrf,
        ]);
    }
}
