<?php

/*
//Debug Mode
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/


defined("BASEPATH") or exit("No direct script access allowed");

/*
Module Name: Financeiro
Description: Módulo de gerenciamento financeiro com conciliação bancária.
Version: 1.0.0
Requires at least: 2.3.*
Author: Wkarts
Author URI: https://t.me/wkartspro
*/

define("finance_MODULE_NAME", "finance");

// Hook de ativação do módulo
register_activation_hook(finance_MODULE_NAME, "finance_module_activation_hook");

// Função de ativação do módulo
function finance_module_activation_hook()
{
    require_once(__DIR__ . '/install.php');
    log_activity('Módulo Financeiro ativado e instalação executada.');
}

// Função de inicialização do menu do módulo
hooks()->add_action("admin_init", "finance_module_init_menu_items");


function finance_module_init_menu_items()
{
    $CI = &get_instance();

    // Menu principal "Financeiro"
    $CI->app_menu->add_sidebar_menu_item("finance-menu", [
        "name" => "Financeiro",
        "href" => "#",
        "icon" => "fa fa-university", // Ícone do menu principal
        "position" => 20,
        "collapse" => true, // Indica que o item terá subitens
    ]);

    // Submenu "Dashboard"
    $CI->app_menu->add_sidebar_children_item("finance-menu", [
        "slug" => "finance-dashboard",
        "name" => "Dashboard",
        "href" => admin_url("finance/dashboard"),
        "position" => 1,
        "icon" => "fa fa-tachometer", // Ícone do submenu "Dashboard"
    ]);

    // Submenu "Bancos"
    $CI->app_menu->add_sidebar_children_item("finance-menu", [
        "slug" => "finance-bancos",
        "name" => "Bancos",
        "href" => admin_url("finance/bank"),
        "position" => 2,
        "icon" => "fa fa-university", // Ícone do submenu "Bancos"
    ]);

    // Submenu "Categorias"
    $CI->app_menu->add_sidebar_children_item("finance-menu", [
        "slug" => "finance-categorias",
        "name" => "Categorias",
        "href" => admin_url("finance/category"),
        "position" => 3,
        "icon" => "fa fa-tags", // Ícone do submenu "Categorias"
    ]);

    // Submenu "Conciliação"
    $CI->app_menu->add_sidebar_children_item("finance-menu", [
        "slug" => "finance-conciliacao",
        "name" => "Conciliação OFX",
        "href" => admin_url("finance/conciliacao/upload_ofx_form"),
        "position" => 4,
        "icon" => "fa fa-exchange", // Ícone do submenu "Conciliação"
    ]);

    // Submenu "Conciliar Faturas"
    $CI->app_menu->add_sidebar_children_item("finance-menu", [
        "slug" => "finance-conciliar-faturas",
        "name" => "Conciliar Faturas",
        "href" => admin_url("finance/ConciliarFaturas"),
        "position" => 4,
        "icon" => "fa fa-file", // Ícone do submenu "Conciliar Faturas"
    ]);
}