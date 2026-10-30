<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(__DIR__ . '/Ib_app_pdf.php');

class Built_invoice_pdf extends Ib_app_pdf
{
    protected $invoice;

    protected $template;

    protected $built_invoice_id;

    private $invoice_number;

    /**
     * Constructs a new instance.
     *
     * @param        $invoice           The invoice
     * @param        $template          The template
     * @param        $built_invoice_id  The built invoice identifier
     * @param      string  $tag               The tag
     */
    public function __construct($invoice, $template, $built_invoice_id, $tag = '')
    {
        $this->load_language($invoice->clientid);
        $invoice                = hooks()->apply_filters('invoice_html_pdf_data', $invoice);
        $GLOBALS['invoice_pdf'] = $invoice;

        parent::__construct();

        if (!class_exists('Invoices_model', false)) {
            $this->ci->load->model('invoices_model');
        }

        $this->tag            = $tag;
        $this->invoice        = $invoice;
        $this->template       = $template;
        $this->built_invoice_id = $built_invoice_id;
        $this->invoice_number = format_invoice_number($this->invoice->id);

        $template_setting = json_decode($template->setting);

        $page = 'P';
        if($template_setting->page_rotation == 'landcape'){
            $page = 'L';
        }

        $this->AddPage($page, 'A4');
        $this->SetTitle($this->invoice_number);

    }

    /**
     * { Header }
     */
    public function Header() { 
        $template_setting = json_decode($this->template->setting);

        if($template_setting->background_color != ''){
            $bgcolor = ib_convert_color($template_setting->background_color);
            if($template_setting->page_rotation == 'landcape'){
                $this->Rect(0,0,297,210,'F','',$fill_color = array($bgcolor[0], $bgcolor[1], $bgcolor[2]));
            }else{
                $this->Rect(0,0,210,297,'F','',$fill_color = array($bgcolor[0], $bgcolor[1], $bgcolor[2]));
            }

        }else{
            if($template_setting->page_rotation == 'landcape'){
                $this->Rect(0,0,297,210,'F','',$fill_color = array(255, 255, 255));
            }else{
                $this->Rect(0,0,210,297,'F','',$fill_color = array(255, 255, 255));
            }
        }
    }

    /**
     * { prepare }
     *
     * @return       ( description_of_the_return_value )
     */
    public function prepare()
    {
        $this->ci->load->model('invoices_builder/invoices_builder_model');
        $this->with_number_to_word($this->invoice->clientid);

        $this->set_view_vars([
            'status'         => $this->invoice->status,
            'invoice_number' => $this->invoice_number,
            'payment_modes'  => $this->get_payment_modes(),
            'invoice'        => $this->invoice,
            'template'       => $this->template,
            'built_invoice_id' => $this->built_invoice_id,
            'built_invoice' => $this->ci->invoices_builder_model->get_built_invoice($this->built_invoice_id),
        ]);

        return $this->build();
    }

    /**
     * { type }
     *
     * @return     string  
     */
    protected function type()
    {
        return 'invoice';
    }

    /**
     * { file path }
     *
     * @return     actualPath
     */
    protected function file_path()
    {
        $actualPath = APP_MODULES_PATH . 'invoices_builder/views/view_invoice/pdf/built_invoice_pdf.php';

        return $actualPath;
    }

    /**
     * Gets the payment modes.
     *
     * @return       The payment modes.
     */
    private function get_payment_modes()
    {
        $this->ci->load->model('payment_modes_model');
        $payment_modes = $this->ci->payment_modes_model->get();

        // In case user want to include {invoice_number} or {client_id} in PDF offline mode description
        foreach ($payment_modes as $key => $mode) {
            if (isset($mode['description'])) {
                $payment_modes[$key]['description'] = str_replace('{invoice_number}', $this->invoice_number, $mode['description']);
                $payment_modes[$key]['description'] = str_replace('{client_id}', $this->invoice->clientid, $mode['description']);
            }
        }

        return $payment_modes;
    }
}
