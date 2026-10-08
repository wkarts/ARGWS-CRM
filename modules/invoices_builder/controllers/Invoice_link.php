<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Invoice_link extends ClientsController
{   
    /**
     * { index }
     *
     * @param      string  $id     The identifier
     * @param        $hash   The hash
     */
    public function index($id, $hash)
    {
        $this->load->model('invoices_model');
        $this->load->model('invoices_builder/invoices_builder_model');
        check_public_invoice_restrictions($id, $hash);
        $invoice_built = $this->invoices_builder_model->get_built_invoice($id);

        $invoice = $this->invoices_model->get($invoice_built->invoice_id);

        $template = $this->invoices_builder_model->get_template($invoice_built->template_id);

        if (!is_client_logged_in()) {
            load_client_language($invoice->clientid);
        }

        // Handle Invoice PDF generator
        if ($this->input->post('invoicepdf')) {
            try {
                $pdf = $this->invoices_builder_model->_pdf_view($invoice, $template, $id);
            } catch (Exception $e) {
                echo html_entity_decode($e->getMessage());
                die;
            }

            $invoice_number = format_invoice_number($invoice->id);
            $companyname    = get_option('invoice_company_name');
            if ($companyname != '') {
                $invoice_number .= '-' . mb_strtoupper(slug_it($companyname), 'UTF-8');
            }
            $pdf->Output(mb_strtoupper(slug_it($invoice_number), 'UTF-8') . '.pdf', 'D');
            die();
        }

        if ($this->input->post('make_payment')) {
            $this->load->model('payments_model');
            if (!$this->input->post('paymentmode')) {
                set_alert('warning', _l('invoice_html_payment_modes_not_selected'));
                redirect(site_url('invoices_builder/invoice_link/index/' . $id . '/' . $hash));
            } elseif ((!$this->input->post('amount') || $this->input->post('amount') == 0) && get_option('allow_payment_amount_to_be_modified') == 1) {
                set_alert('warning', _l('invoice_html_amount_blank'));
                redirect(site_url('invoices_builder/invoice_link/index/' . $id . '/' . $hash));
            }
            $this->payments_model->process_payment($this->input->post(), $invoice_built->invoice_id);
        }

        if ($this->input->post('paymentpdf')) {
            $payment = $this->payments_model->get($this->input->post('paymentpdf'));
            // Confirm that the payment is related to the invoice.
            if ($payment->invoiceid == $invoice->id) {
                $payment->invoice_data = $this->invoices_model->get($payment->invoiceid);
                $paymentpdf            = payment_pdf($payment);
                $paymentpdf->Output(mb_strtoupper(slug_it(_l('payment') . '-' . $payment->paymentid), 'UTF-8') . '.pdf', 'D');
                die;
            }
        }

        $this->app_scripts->theme('sticky-js', 'assets/plugins/sticky/sticky.js');
        $this->load->library('app_number_to_word', [
            'clientid' => $invoice->clientid,
        ], 'numberword');
        $this->load->model('payment_modes_model');
        $this->load->model('payments_model');
        $data['payments']      = $this->payments_model->get_invoice_payments($id);
        $data['payment_modes'] = $this->payment_modes_model->get();
        $data['title']         = format_invoice_number($invoice->id);
        $this->disableNavigation();
        $this->disableSubMenu();
        $data['hash']      = $hash;
        $data['invoice']   = hooks()->apply_filters('invoice_html_pdf_data', $invoice);
        $data['template'] = $this->invoices_builder_model->get_template($invoice_built->template_id);
        $data['built_invoice'] = $invoice_built;
        $data['built_invoice_id'] = $id;

        $data['bodyclass'] = 'viewinvoice';
        $this->data($data);
        $this->view('view_invoice/html/index');
        no_index_customers_area();
        $this->layout();
    }
}
