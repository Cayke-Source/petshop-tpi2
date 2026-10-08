<?php
/*
 * Cadastro de Produtos / Estoque (tabela futura: produtos — ligada a fornecedores)
 * Lógica adicional: calcula o preço de venda a partir do custo e da margem,
 * o valor parado em estoque, sugere reposição quando abaixo do mínimo e
 * controla a validade (rações e medicamentos vencem).
 */

declare(strict_types=1);
require __DIR__ . '/validacao.php';

exigirPost();
$v = new Validador();

$codigo = strtoupper(campo('codigo'));
if ($v->obrigatorio('codigo', 'Código') && !preg_match('/^[A-Z]{3}-\d{4}$/', $codigo)) {
    $v->erro('codigo', 'Código no formato AAA-0000 (ex.: RAC-0012).');
}

if ($v->obrigatorio('descricao', 'Descrição')) {
    $v->tamanho('descricao', 'Descrição', 3, 120);
}

if ($v->obrigatorio('cnpj_fornecedor', 'CNPJ do fornecedor') && !cnpjValido(campo('cnpj_fornecedor'))) {
    $v->erro('cnpj_fornecedor', 'CNPJ do fornecedor inválido.');
}

$custo = null;
if ($v->obrigatorio('preco_custo', 'Preço de custo')) {
    $custo = $v->numero('preco_custo', 'Preço de custo', 0.01, 100000);
}

$margem = null;
if ($v->obrigatorio('margem', 'Margem de lucro')) {
    $margem = $v->numero('margem', 'Margem de lucro', 0, 300);
}

$quantidade = null;
if ($v->obrigatorio('quantidade', 'Quantidade em estoque')) {
    $quantidade = $v->inteiro('quantidade', 'Quantidade em estoque', 0, 100000);
}

$minimo = null;
if ($v->obrigatorio('estoque_minimo', 'Estoque mínimo')) {
    $minimo = $v->inteiro('estoque_minimo', 'Estoque mínimo', 1, 10000);
}

// Validade é opcional (brinquedos e acessórios não vencem)
$validade = null;
if (campo('validade') !== '') {
    $validade = $v->data('validade', 'Validade');
}

$v->encerrarSeHouverErros();

// ----- Lógica adicional -----
$precoVenda = round($custo * (1 + $margem / 100), 2);
$valorEstoque = $custo * $quantidade;
$alertas = [];

if ($quantidade === 0) {
    $status = 'SEM ESTOQUE';
} elseif ($quantidade < $minimo) {
    $status = 'Crítico (abaixo do mínimo)';
} elseif ($quantidade <= $minimo * 1.2) {
    $status = 'Atenção (perto do mínimo)';
} else {
    $status = 'Normal';
}

if ($quantidade < $minimo) {
    $sugestao = ($minimo * 2) - $quantidade; // repõe até o dobro do mínimo
    $alertas[] = "Sugestão de compra: {$sugestao} unidade(s) — custo estimado " . dinheiro($sugestao * $custo) . '.';
}
if ($margem < 15) {
    $alertas[] = 'Margem abaixo de 15%: o preço pode não cobrir os custos da loja.';
}

$textoValidade = 'Não se aplica';
if ($validade !== null) {
    $hoje = new DateTimeImmutable('today');
    $dias = (int)$hoje->diff($validade)->format('%r%a'); // negativo = já venceu
    if ($dias < 0) {
        $textoValidade = $validade->format('d/m/Y') . ' — VENCIDO';
        $alertas[] = 'Produto vencido: retire da prateleira e não venda.';
    } elseif ($dias <= 30) {
        $promo = round($precoVenda * 0.8, 2);
        $textoValidade = $validade->format('d/m/Y') . " — vence em {$dias} dia(s)";
        $alertas[] = 'Perto do vencimento: sugestão de promoção (20% off) por ' . dinheiro($promo) . '.';
    } else {
        $textoValidade = $validade->format('d/m/Y') . " ({$dias} dias)";
    }
}

responder(200, [
    'ok' => true,
    'mensagem' => 'Produto validado com sucesso!',
    'resumo' => [
        'Código' => $codigo,
        'Descrição' => campo('descricao'),
        'Preço de custo' => dinheiro($custo),
        'Preço de venda' => dinheiro($precoVenda) . ' (lucro de ' . dinheiro($precoVenda - $custo) . ' por unidade)',
        'Valor em estoque (custo)' => dinheiro($valorEstoque),
        'Situação do estoque' => $status . " — {$quantidade} un. (mínimo {$minimo})",
        'Validade' => $textoValidade,
    ],
    'alertas' => $alertas,
]);
