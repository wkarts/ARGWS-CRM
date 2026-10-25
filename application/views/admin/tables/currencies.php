<?php

defined('BASEPATH') or exit('No direct script access allowed');
$aColumns = [
    'name',
    'symbol',
];
$sIndexColumn = 'id';
$sTable       = db_prefix() . 'currencies';
if(isset($native_currency_rows)){$output=['draw'=>$native_currency_draw,'iTotalRecords'=>$native_currency_total,'iTotalDisplayRecords'=>$native_currency_filtered,'aaData'=>[]];$rResult=$native_currency_rows;}else{
$result       = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], [
    'id',
    'isdefault',
    'placement',
    'thousand_separator',
    'decimal_separator',
]);
$output  = $result['output'];
$rResult = $result['rResult'];
}

foreach ($rResult as $aRow) {
    $row = [];

    for ($i = 0; $i < count($aColumns); $i++) {
        $_data = $aRow[$aColumns[$i]];

        $attributes = [
            'data-toggle'             => 'modal',
            'data-target'             => '#currency_modal',
            'data-id'                 => $aRow['id'],
            'data-placement'          => $aRow['placement'],
            'data-thousand-separator' => $aRow['thousand_separator'],
            'data-decimal-separator'  => $aRow['decimal_separator'],
        ];

        if ($aColumns[$i] == 'name') {
            $_data = '<span class="name"><a href="#" ' . _attributes_to_string($attributes) . ' class="tw-font-medium">' . e($_data) . '</a></span>';
            if ($aRow['isdefault'] == 1) {
                $_data .= '<span class="display-block text-info">' . _l('base_currency_string') . '</span>';
            }
        }
        $row[] = $_data;
    }

    $options = '<div class="tw-flex tw-items-center tw-space-x-2">';
    $options .= '<a href="#' . $aRow['id'] . '" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700" ' . _attributes_to_string($attributes) . '>
        <i class="fa-regular fa-pen-to-square fa-lg"></i>
    </a>';

    if(isset($native_currency_rows)){
        foreach(['make_base_currency'=>'fa-star','delete'=>'fa-trash-can']as$method=>$icon){if($method==='make_base_currency'&&$aRow['isdefault']==1)continue;
            $options.='<form method="post" action="'.e(admin_url('currencies/'.$method.'/'.$aRow['id'])).'"><input type="hidden" name="_company_context" value="'.e(CompanyRequestScope::token()).'"><input type="hidden" name="_native_company_csrf" value="'.e(CompanyRequestScope::nativeCsrfToken($this)).'"><button type="submit" class="btn btn-link" aria-label="'.e(_l($method==='delete'?'delete':'make_base_currency')).'"><i class="fa-regular '.$icon.' fa-lg"></i></button></form>';
        }
    }else{
    if ($aRow['isdefault'] == 0) {
        $options .= '<a href="' . admin_url('currencies/make_base_currency/' . $aRow['id']) . '" class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700" ' . _attributes_to_string([
            'data-toggle' => 'tooltip',
            'title'       => _l('make_base_currency'),
        ]) . '>
        <i class="fa-regular fa-star fa-lg"></i>
    </a>';
    }

    $options .= '<a href="' . admin_url('currencies/delete/' . $aRow['id']) . '"
    class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete">
        <i class="fa-regular fa-trash-can fa-lg"></i>
    </a>';

    }
    $options .= '</div>';

    $row[]              = $options;
    $output['aaData'][] = $row;
}

if(isset($native_currency_rows))echo json_encode($output,JSON_THROW_ON_ERROR);
