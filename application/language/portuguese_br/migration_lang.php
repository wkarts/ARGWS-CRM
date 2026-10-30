<?php
/**
 * CodeIgniter
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2019, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @license https://opensource.org/licenses/MIT MIT License
 */
defined('BASEPATH') or exit('No direct script access allowed');

$lang['migration_none_found'] = 'Nenhuma migration foi encontrada.';
$lang['migration_not_found'] = 'Não foi possível localizar a migration com o número de versão: %s.';
$lang['migration_sequence_gap'] = 'Há uma lacuna na sequência de migrations próxima à versão: %s.';
$lang['migration_multiple_version'] = 'Há mais de uma migration com o mesmo número de versão: %s.';
$lang['migration_class_doesnt_exist'] = 'A classe de migration "%s" não foi encontrada.';
$lang['migration_missing_up_method'] = 'A classe de migration "%s" não possui o método "up".';
$lang['migration_missing_down_method'] = 'A classe de migration "%s" não possui o método "down".';
$lang['migration_invalid_filename'] = 'O arquivo de migration "%s" tem um nome inválido.';
