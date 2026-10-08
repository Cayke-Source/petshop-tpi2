<?php
/*
 * Cadastro de Funcionários (tabela futura: funcionarios)
 * Lógica adicional: exige CRMV quando a função é veterinário, confere o piso
 * salarial da função, calcula o tempo de casa e o nível (júnior, pleno, sênior)
 * e o custo mensal aproximado com encargos.
 */

declare(strict_types=1);
require __DIR__ . '/validacao.php';

exigirPost();
$v = new Validador();

const ENCARGOS = 0.68; // estimativa de encargos trabalhistas sobre o salário
// função => [nome exibido, piso salarial]
$funcoes = [
    'atendente'   => ['Atendente', 1650.0],
    'banhista'    => ['Banhista', 1700.0],
    'tosador'     => ['Tosador(a)', 2100.0],
    'adestrador'  => ['Adestrador(a)', 2300.0],
    'veterinario' => ['Médico(a) veterinário(a)', 4500.0],
];

if ($v->obrigatorio('nome', 'Nome')) {
    $v->tamanho('nome', 'Nome', 3, 100);
    if (!preg_match('/^[\p{L} \'.-]+$/u', campo('nome'))) {
        $v->erro('nome', 'O nome deve conter apenas letras.');
    }
}

if ($v->obrigatorio('cpf', 'CPF') && !cpfValido(campo('cpf'))) {
    $v->erro('cpf', 'CPF inválido.');
}

if ($v->obrigatorio('email', 'E-mail')) {
    $v->email('email');
}

if ($v->obrigatorio('funcao', 'Função')) {
    $v->opcao('funcao', 'Função', array_keys($funcoes));
}

$admissao = null;
if ($v->obrigatorio('data_admissao', 'Data de admissão')) {
    $admissao = $v->data('data_admissao', 'Data de admissão');
    if ($admissao !== null && $admissao > new DateTimeImmutable('today')) {
        $v->erro('data_admissao', 'A data de admissão não pode estar no futuro.');
    }
}

$salario = null;
if ($v->obrigatorio('salario', 'Salário')) {
    $salario = $v->numero('salario', 'Salário', 1, 100000);
}

// Regra: salário não pode ficar abaixo do piso da função
if ($salario !== null && !$v->temErro('funcao')) {
    [$nomeFuncao, $piso] = $funcoes[campo('funcao')];
    if ($salario < $piso) {
        $v->erro('salario', "O piso para {$nomeFuncao} é " . dinheiro($piso) . '.');
    }
}

// Regra: veterinário precisa de CRMV (ex.: MG-12345)
$crmv = strtoupper(campo('crmv'));
if (campo('funcao') === 'veterinario') {
    if ($crmv === '') {
        $v->erro('crmv', 'O CRMV é obrigatório para veterinários.');
    } elseif (!preg_match('/^[A-Z]{2}-\d{3,6}$/', $crmv)) {
        $v->erro('crmv', 'CRMV no formato UF-número (ex.: MG-12345).');
    }
}

$v->encerrarSeHouverErros();

// ----- Lógica adicional -----
$tempo = $admissao->diff(new DateTimeImmutable('today'));
if ($tempo->y >= 5) {
    $nivel = 'Sênior';
} elseif ($tempo->y >= 2) {
    $nivel = 'Pleno';
} else {
    $nivel = 'Júnior';
}

[$nomeFuncao] = $funcoes[campo('funcao')];
$resumo = [
    'Nome' => campo('nome'),
    'CPF' => formatarCpf(campo('cpf')),
    'Função' => $nomeFuncao,
    'Tempo de casa' => "{$tempo->y} ano(s) e {$tempo->m} mês(es)",
    'Nível' => $nivel,
    'Custo mensal estimado' => dinheiro($salario * (1 + ENCARGOS)) . ' (salário + encargos)',
];
if ($crmv !== '' && campo('funcao') === 'veterinario') {
    $resumo['CRMV'] = $crmv;
}

$alertas = [];
if ($tempo->y === 0 && $tempo->m < 3) {
    $alertas[] = 'Funcionário em período de experiência (primeiros 90 dias).';
}

responder(200, [
    'ok' => true,
    'mensagem' => 'Funcionário validado com sucesso!',
    'resumo' => $resumo,
    'alertas' => $alertas,
]);
