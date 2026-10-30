<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Invoice_built_merge_fields extends App_merge_fields
{
    public function build()
    {
        return [
            [
                'name'      => 'Invoice number',
                'key'       => '{invoice_number}',
                'available' => [
                    'invoice_builder',
                ],
                'templates' => [
                    'invoice-to-contact',
                ],
            ],
            [
                'name'      => 'Public link',
                'key'       => '{public_link}',
                'available' => [
                    'invoice_builder',
                ],
                'templates' => [
                    'invoice-to-contact',
                ],
            ],
            [
                'name'      => 'Additional content',
                'key'       => '{additional_content}',
                'available' => [
                    'invoice_builder',
                ],
                'templates' => [
                    'invoice-to-contact',
                ],
            ],
        ];
    }

    /**
     * Merge field for appointments
     * @param  mixed $teampassword 
     * @return array
     */
    public function format($data)
    {
        $built_inv_id = $data->built_inv_id;
        $this->ci->load->model('invoices_builder/invoices_builder_model');

        $fields = [];

        $built_inv = $this->ci->invoices_builder_model->get_built_invoice($built_inv_id);
        
        if (!$built_inv) {
            return $fields;
        }

        $fields['{public_link}']                  = site_url('invoices_builder/invoice_link/index/' . $built_inv->id.'/'.$built_inv->hash);
        $fields['{invoice_number}']                  =  format_invoice_number($built_inv->invoice_id);
        $fields['{additional_content}'] = $data->content;

        return $fields;
    }
}
