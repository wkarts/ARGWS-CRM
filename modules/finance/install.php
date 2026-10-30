<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI =& get_instance();
$CI->load->database();

try {
    // Criação da tabela tblconciliacoes
    if (!$CI->db->table_exists(db_prefix() . 'conciliacoes')) {
        $CI->db->query('CREATE TABLE `' . db_prefix() . 'conciliacoes` (
            `id` int NOT NULL AUTO_INCREMENT,
            `ofx_id` varchar(50) COLLATE utf8mb4_bin NOT NULL,
            `data` date NOT NULL,
            `descricao` varchar(255) COLLATE utf8mb4_bin NOT NULL,
            `valor` decimal(10,2) NOT NULL,
            `tipo` enum(\'entrada\',\'saida\',\'transferencia\') COLLATE utf8mb4_bin NOT NULL,
            `categoria_id` int DEFAULT NULL,
            `banco_id` int DEFAULT NULL,
            `banco_origem_id` int DEFAULT NULL,
            `conciliado` tinyint(1) DEFAULT \'0\',
            `tipo_conciliacao` enum(\'OFX\',\'Manual\') COLLATE utf8mb4_bin NOT NULL DEFAULT \'Manual\',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;');
        log_activity('Tabela `tblconciliacoes` criada.');
    } else {
        log_activity('Tabela `tblconciliacoes` já existe.');
    }

    // Criação da tabela tblcategories
    if (!$CI->db->table_exists(db_prefix() . 'categories')) {
        $CI->db->query('CREATE TABLE `' . db_prefix() . 'categories` (
            `id` int NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8mb4_bin NOT NULL,
            `type` enum(\'entrada\',\'saida\',\'outros\') COLLATE utf8mb4_bin NOT NULL,
            `father_category` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;');
        log_activity('Tabela `tblcategories` criada.');

       
       // Inserção dos dados iniciais na tabela tblcategories
$CI->db->query('INSERT INTO `' . db_prefix() . 'categories` (`id`, `name`, `type`, `father_category`) VALUES
(1, \'13°Salário - 1ª Parcela\', \'saida\', \'Despesas com Salários e Encargos\'),
(2, \'13°Salário - 2ª Parcela\', \'saida\', \'Despesas com Salários e Encargos\'),
(3, \'Adiantamento Salarial\', \'saida\', \'Despesas com Salários e Encargos\'),
(4, \'Água e Saneamento\', \'saida\', \'Despesas com Imóvel\'),
(5, \'Aluguel\', \'saida\', \'Despesas com Imóvel\'),
(6, \'Alvará de Funcionamento\', \'saida\', \'Despesas com Imóvel\'),
(7, \'Antecipação de Lucros\', \'saida\', \'Despesas com Diretoria\'),
(8, \'Assessoria de Imprensa\', \'saida\', \'Despesas Comerciais e com marketing\'),
(9, \'Benfeitorias em Bens de Terceiros\', \'saida\', \'Bens Imobilizados da Empresa\'),
(10, \'Bens de Pequeno Valor\', \'saida\', \'Despesas Administrativas\'),
(11, \'Bens Imobilizados da Empresa\', \'saida\', NULL),
(12, \'Brindes para Clientes\', \'saida\', \'Despesas Comerciais e com marketing\'),
(13, \'Cancelamentos\', \'saida\', \'Deduções da Receita\'),
(14, \'Cartório\', \'saida\', \'Despesas Administrativas\'),
(15, \'Combustíveis\', \'saida\', \'Despesas com Veículos\'),
(16, \'Comissões de Vendedores\', \'saida\', \'Despesas com Vendas e Serviços\'),
(17, \'Computadores e Periféricos\', \'saida\', \'Bens Imobilizados da Empresa\'),
(18, \'Condomínio\', \'saida\', \'Despesas com Imóvel\'),
(19, \'Confraternizações\', \'saida\', \'Despesas com Colaboradores\'),
(20, \'Construções em Andamento - Imóvel Próprio\', \'saida\', \'Bens Imobilizados da Empresa\'),
(21, \'Contribuição Sindical\', \'saida\', \'Despesas com Colaboradores\'),
(22, \'Copa e Cozinha\', \'saida\', \'Despesas Administrativas\'),
(23, \'Correios\', \'saida\', \'Despesas Administrativas\'),
(24, \'Cursos e Treinamentos\', \'saida\', \'Despesas com Colaboradores\'),
(25, \'Deduções da Receita\', \'saida\', NULL),
(26, \'Descontos\', \'saida\', \'Despesas com Vendas e Serviços\'),
(27, \'Despesas Administrativas\', \'saida\', NULL),
(28, \'Despesas com Colaboradores\', \'saida\', NULL),
(29, \'Despesas com Diretoria\', \'saida\', NULL),
(30, \'Despesas com Imóvel\', \'saida\', NULL),
(31, \'Despesas com Salários e Encargos\', \'saida\', NULL),
(32, \'Despesas com Veículos\', \'saida\', NULL),
(33, \'Despesas com Vendas e Serviços\', \'saida\', NULL),
(34, \'Despesas Comerciais e com marketing\', \'saida\', NULL),
(35, \'Despesas Financeiras\', \'saida\', NULL),
(36, \'Despesas Pessoais dos Sócios\', \'saida\', \'Despesas com Diretoria\'),
(37, \'Devoluções\', \'saida\', \'Deduções da Receita\'),
(38, \'Edifícios e Construções\', \'saida\', \'Bens Imobilizados da Empresa\'),
(39, \'Empréstimos de Bancos\', \'saida\', \'Empréstimos e Financiamentos\'),
(40, \'Empréstimos de Outras Instituições\', \'saida\', \'Empréstimos e Financiamentos\'),
(41, \'Empréstimos de Sócios\', \'saida\', \'Empréstimos e Financiamentos\'),
(42, \'Empréstimos e Financiamentos\', \'saida\', NULL),
(43, \'Energia Elétrica\', \'saida\', \'Despesas com Imóvel\'),
(44, \'Estacionamento\', \'saida\', \'Despesas com Veículos\'),
(45, \'Exames Médicos\', \'saida\', \'Despesas com Colaboradores\'),
(46, \'Facebook Ads\', \'saida\', \'Despesas Comerciais e com marketing\'),
(47, \'Farmácia\', \'saida\', \'Despesas com Colaboradores\'),
(48, \'Férias\', \'saida\', \'Despesas com Salários e Encargos\'),
(49, \'FGTS e Multa de FGTS\', \'saida\', \'Despesas com Salários e Encargos\'),
(50, \'Frete\', \'saida\', \'Despesas com Vendas e Serviços\'),
(51, \'Google Ads\', \'saida\', \'Despesas Comerciais e com marketing\'),
(52, \'Gratificações\', \'saida\', \'Despesas com Colaboradores\'),
(53, \'Honorários (outros)\', \'saida\', \'Despesas Administrativas\'),
(54, \'Honorários Advocatícios\', \'saida\', \'Despesas Administrativas\'),
(55, \'Honorários Consultoria\', \'saida\', \'Despesas Administrativas\'),
(56, \'Honorários Contábeis\', \'saida\', \'Despesas Administrativas\'),
(57, \'ICMS ST sobre Vendas\', \'saida\', \'Impostos sobre Vendas e sobre Serviços\'),
(58, \'Impostos sobre Aplicações\', \'saida\', \'Despesas Financeiras\'),
(59, \'Impostos sobre transações (IOF)\', \'saida\', \'Despesas Financeiras\'),
(60, \'Impostos sobre Vendas e sobre Serviços\', \'saida\', NULL),
(61, \'INSS sobre Pró-labore - GPS\', \'saida\', \'Despesas com Diretoria\'),
(62, \'INSS sobre Salários - GPS\', \'saida\', \'Despesas com Salários e Encargos\'),
(63, \'Internet\', \'saida\', \'Despesas Administrativas\'),
(64, \'IPTU\', \'saida\', \'Despesas com Imóvel\'),
(65, \'IPVA / DPVAT / Licenciamento\', \'saida\', \'Despesas com Veículos\'),
(66, \'IRRF s/ Salários - DARF 0561\', \'saida\', \'Despesas com Salários e Encargos\'),
(67, \'IRRF sobre Pró-labore - Darf\', \'saida\', \'Despesas com Diretoria\'),
(68, \'ISS sobre Faturamento\', \'saida\', \'Impostos sobre Vendas e sobre Serviços\'),
(69, \'Juros Conta Garantida\', \'saida\', \'Empréstimos e Financiamentos\'),
(70, \'Lanches e Refeições\', \'saida\', \'Despesas Administrativas\'),
(71, \'Leasing - Imóveis\', \'saida\', \'Bens Imobilizados da Empresa\'),
(72, \'Leasing - Máquinas, Equipamentos e Instalações Industriais\', \'saida\', \'Bens Imobilizados da Empresa\'),
(73, \'Leasing - Móveis, Utensílios e Instalações Administrativos\', \'saida\', \'Bens Imobilizados da Empresa\'),
(74, \'Leasing - Móveis, Utensílios e Instalações Comerciais\', \'saida\', \'Bens Imobilizados da Empresa\'),
(75, \'Leasing - Outras Imobilizações\', \'saida\', \'Bens Imobilizados da Empresa\'),
(76, \'Leasing - Veículos\', \'saida\', \'Bens Imobilizados da Empresa\'),
(77, \'Manutenção de Equipamentos\', \'saida\', \'Despesas Administrativas\'),
(78, \'Manutenção de Veículos\', \'saida\', \'Despesas com Veículos\'),
(79, \'Manutenção Predial\', \'saida\', \'Despesas com Imóvel\'),
(80, \'Máquinas, Equipamentos e Instalações Industriais\', \'saida\', \'Bens Imobilizados da Empresa\'),
(81, \'Marketing e Publicidade\', \'saida\', \'Despesas Comerciais e com marketing\'),
(82, \'Materiais Aplicados na Prestação de Serviços\', \'saida\', \'Despesas com Vendas e Serviços\'),
(83, \'Material de Escritório\', \'saida\', \'Despesas Administrativas\'),
(84, \'Material de Limpeza e Higiene\', \'saida\', \'Despesas Administrativas\'),
(85, \'Móveis, Utensílios e Instalações Administrativos\', \'saida\', \'Bens Imobilizados da Empresa\'),
(86, \'Móveis, Utensílios e Instalações Comerciais\', \'saida\', \'Bens Imobilizados da Empresa\'),
(87, \'Multas de Trânsito\', \'saida\', \'Despesas com Veículos\'),
(88, \'Outras despesas com imóvel\', \'saida\', \'Despesas com Imóvel\'),
(89, \'Outras despesas com marketing\', \'saida\', \'Despesas Comerciais e com marketing\'),
(90, \'Outras Imobilizações por Aquisição\', \'saida\', \'Bens Imobilizados da Empresa\'),
(91, \'Parcelamento do Simples Nacional\', \'saida\', \'Parcelamentos e Dívidas\'),
(92, \'Parcelamentos e Dívidas\', \'saida\', NULL),
(93, \'Pedágios\', \'saida\', \'Despesas com Veículos\'),
(94, \'Plano de Saúde Colaboradores\', \'saida\', \'Despesas com Colaboradores\'),
(95, \'Plano de Saúde Sócios\', \'saida\', \'Despesas com Diretoria\'),
(96, \'Plano Odontológico Colaboradores\', \'saida\', \'Despesas com Colaboradores\'),
(97, \'Plano Odontológico Sócios\', \'saida\', \'Despesas com Diretoria\'),
(98, \'PLR - Participação nos Lucros e Resultados\', \'saida\', \'Despesas com Salários e Encargos\'),
(99, \'Pró-Labore\', \'saida\', \'Despesas com Diretoria\'),
(100, \'Remuneração de Autônomos\', \'saida\', \'Despesas com Salários e Encargos\'),
(101, \'Remuneração de Estagiários\', \'saida\', \'Despesas com Salários e Encargos\'),
(102, \'Rescisões\', \'saida\', \'Despesas com Salários e Encargos\'),
(103, \'Retenção - Darf 1708 - IRRF\', \'saida\', \'Despesas Administrativas\'),
(104, \'Retenção - Darf 3208 - IRRF Aluguel\', \'saida\', \'Despesas com Imóvel\'),
(105, \'Retenção - Darf 5952 - PIS/COFINS/CSLL\', \'saida\', \'Despesas Administrativas\'),
(106, \'Retenção - GPS 2631 - INSS\', \'saida\', \'Despesas Administrativas\'),
(107, \'Retenção - ISS Serviços Tomados\', \'saida\', \'Despesas Administrativas\'),
(108, \'Salários\', \'saida\', \'Despesas com Salários e Encargos\'),
(109, \'Seguro de Imóveis\', \'saida\', \'Despesas com Imóvel\'),
(110, \'Seguro de Vida\', \'saida\', \'Despesas com Colaboradores\'),
(111, \'Seguros de Veículos\', \'saida\', \'Despesas com Veículos\'),
(112, \'Simples Nacional - DAS\', \'saida\', \'Impostos sobre Vendas e sobre Serviços\'),
(113, \'Software / Licença de Uso\', \'saida\', \'Bens Imobilizados da Empresa\'),
(114, \'Software / Licença de Uso\', \'saida\', \'Despesas Comerciais e com marketing\'),
(115, \'Tarifas Bancárias\', \'saida\', \'Despesas Financeiras\'),
(116, \'Tarifas de Boletos\', \'saida\', \'Despesas Financeiras\'),
(117, \'Tarifas de Cartões de Crédito\', \'saida\', \'Despesas Financeiras\'),
(118, \'Tarifas DOC / TED\', \'saida\', \'Despesas Financeiras\'),
(119, \'Taxa de Lixo\', \'saida\', \'Despesas com Imóvel\'),
(120, \'Telefonia\', \'saida\', \'Despesas Administrativas\'),
(121, \'Telefonia Móvel\', \'saida\', \'Despesas Administrativas\'),
(122, \'Terrenos\', \'saida\', \'Bens Imobilizados da Empresa\'),
(123, \'Transporte de Mercadorias Vendidas\', \'saida\', \'Despesas com Vendas e Serviços\'),
(124, \'Transporte Urbano (táxi, Uber)\', \'saida\', \'Despesas Administrativas\'),
(125, \'Uniformes\', \'saida\', \'Despesas com Colaboradores\'),
(126, \'Vale-Alimentação\', \'saida\', \'Despesas com Colaboradores\'),
(127, \'Vale-Transporte\', \'saida\', \'Despesas com Colaboradores\'),
(128, \'Veículos\', \'saida\', \'Bens Imobilizados da Empresa\'),
(129, \'Viagens e Representações\', \'saida\', \'Despesas Comerciais e com marketing\'),
(130, \'Vigilância e Segurança Patrimonial\', \'saida\', \'Despesas com Imóvel\'),
(131, \'Empréstimos\', \'entrada\', \'Outras Receitas e Entradas\'),
(132, \'Integralização de Capital Social\', \'entrada\', \'Outras Receitas e Entradas\'),
(133, \'Outras Receitas e Entradas\', \'entrada\', NULL),
(134, \'Receitas de Serviços\', \'entrada\', \'Receitas de Vendas e de Serviços\'),
(135, \'Receitas de Vendas\', \'entrada\', \'Receitas de Vendas e de Serviços\'),
(136, \'Receitas de Vendas e de Serviços\', \'entrada\', NULL),
(137, \'Receitas Financeiras\', \'entrada\', NULL),
(138, \'Rendimentos de Aplicações\', \'entrada\', \'Receitas Financeiras\'),
(139, \'Outros\', \'outros\', NULL);');
log_activity('Dados iniciais inseridos na tabela `tblcategories`.');
    } else {
        log_activity('Tabela `tblcategories` já existe.');
    }

    // Criação da tabela tblbanks
    if (!$CI->db->table_exists(db_prefix() . 'banks')) {
        $CI->db->query('CREATE TABLE `' . db_prefix() . 'banks` (
            `id` int NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `initial_balance` decimal(15,2) NOT NULL,
            `image` varchar(255) DEFAULT NULL,
            `account_number` varchar(50) NOT NULL,
            `agency` varchar(50) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;');
        log_activity('Tabela `tblbanks` criada.');
    } else {
        log_activity('Tabela `tblbanks` já existe.');
    }

} catch (Exception $e) {
    log_activity('Erro ao criar tabelas ou adicionar dados: ' . $e->getMessage());
}
