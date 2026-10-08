<?php
/*
 * Cadastro de Fornecedores (tabela futura: fornecedores)
 * Lógica adicional: valida o CNPJ pelos dígitos verificadores, classifica o
 * fornecedor pelo prazo de entrega e calcula a data prevista de chegada de um
 * pedido feito hoje (contando apenas dias úteis).
 */

declare(strict_types=1);
require __DIR__ . '/validacao.php';

exigirPost();
$v = new Validador();

$categorias = [
    'racoes'       => 'Rações e petiscos',
    'medicamentos' => 'Medicamentos e vacinas',
    'higiene'      => 'Higiene e beleza (shampoos, perfumes)',
    'acessorios'   => 'Acessórios (coleiras, camas, caixas)',
    'brinquedos'   => 'Brinquedos',
    'diversos'     => 'Diversos',
];

if ($v->obrigatorio('razao_social', 'Razão social')) {
    $v->tamanho('razao_social', 'Razão social', 3, 120);
}

if ($v->obrigatorio('cnpj', 'CNPJ') && !cnpjValido(campo('cnpj'))) {
    $v->erro('cnpj', 'CNPJ inválido (dígitos verificadores não conferem).');
}

if ($v->obrigatorio('email', 'E-mail')) {
    $v->email('email');
}

$telefone = apenasDigitos(campo('telefone'));
if ($v->obrigatorio('telefone', 'Telefone') && (strlen($telefone) < 10 || strlen($telefone) > 11)) {
    $v->erro('telefone', 'Telefone deve ter DDD + número (10 ou 11 dígitos).');
}

if ($v->obrigatorio('categoria', 'Categoria')) {
    $v->opcao('categoria', 'Categoria', array_keys($categorias));
}

$prazo = null;
if ($v->obrigatorio('prazo_entrega', 'Prazo de entrega')) {
    $prazo = $v->inteiro('prazo_entrega', 'Prazo de entrega', 1, 90);
}

$pedidoMinimo = null;
if ($v->obrigatorio('pedido_minimo', 'Pedido mínimo')) {
    $pedidoMinimo = $v->numero('pedido_minimo', 'Pedido mínimo', 0, 1000000);
}

$v->encerrarSeHouverErros();

// ----- Lógica adicional -----
if ($prazo <= 2) {
    $classe = 'Expresso (até 2 dias úteis)';
} elseif ($prazo <= 7) {
    $classe = 'Normal (3 a 7 dias úteis)';
} else {
    $classe = 'Longo prazo (mais de 7 dias úteis)';
}

// Soma dias úteis à data de hoje, pulando sábados e domingos
$chegada = new DateTimeImmutable('today');
$diasContados = 0;
while ($diasContados < $prazo) {
    $chegada = $chegada->modify('+1 day');
    $diaSemana = (int)$chegada->format('N'); // 1 = segunda ... 7 = domingo
    if ($diaSemana < 6) {
        $diasContados++;
    }
}

$alertas = [];
if ($prazo > 7) {
    $alertas[] = 'Prazo longo: mantenha estoque de segurança maior para os produtos deste fornecedor.';
}
if ($pedidoMinimo > 5000) {
    $alertas[] = 'Pedido mínimo alto: concentre as compras para atingir o valor.';
}

responder(200, [
    'ok' => true,
    'mensagem' => 'Fornecedor validado com sucesso!',
    'resumo' => [
        'Razão social' => campo('razao_social'),
        'CNPJ' => formatarCnpj(campo('cnpj')),
        'Categoria' => $categorias[campo('categoria')],
        'Classificação' => $classe,
        'Pedido feito hoje chega em' => $chegada->format('d/m/Y'),
        'Pedido mínimo' => dinheiro($pedidoMinimo),
    ],
    'alertas' => $alertas,
]);
