<?php
/*
 * Cadastro de Animais (tabela futura: animais — ligada a tutores pelo CPF)
 * Lógica adicional: confere se o peso é possível para a espécie, calcula a
 * idade, define a fase da vida (filhote, adulto, idoso), o porte (para cães)
 * e avisa sobre vacinação de filhotes.
 */

declare(strict_types=1);
require __DIR__ . '/validacao.php';

exigirPost();
$v = new Validador();

// espécie => [nome exibido, peso mínimo kg, peso máximo kg, idade em que vira idoso]
$especies = [
    'cao'    => ['Cão', 0.5, 100, 8],
    'gato'   => ['Gato', 0.3, 15, 10],
    'ave'    => ['Ave', 0.01, 3, 8],
    'roedor' => ['Roedor', 0.02, 2, 2],
    'coelho' => ['Coelho', 0.5, 8, 6],
];

if ($v->obrigatorio('cpf_tutor', 'CPF do tutor') && !cpfValido(campo('cpf_tutor'))) {
    $v->erro('cpf_tutor', 'CPF do tutor inválido.');
}

if ($v->obrigatorio('nome', 'Nome do animal')) {
    $v->tamanho('nome', 'Nome do animal', 2, 40);
}

if ($v->obrigatorio('especie', 'Espécie')) {
    $v->opcao('especie', 'Espécie', array_keys($especies));
}

if ($v->obrigatorio('raca', 'Raça')) {
    $v->tamanho('raca', 'Raça', 3, 50);
}

if ($v->obrigatorio('sexo', 'Sexo')) {
    $v->opcao('sexo', 'Sexo', ['M', 'F']);
}

$nascimento = null;
if ($v->obrigatorio('data_nascimento', 'Data de nascimento')) {
    $nascimento = $v->data('data_nascimento', 'Data de nascimento');
    if ($nascimento !== null && $nascimento > new DateTimeImmutable('today')) {
        $v->erro('data_nascimento', 'A data de nascimento não pode estar no futuro.');
    }
}

$peso = null;
if ($v->obrigatorio('peso', 'Peso')) {
    $peso = $v->numero('peso', 'Peso', 0.01, 100);
}

// Regra: o peso precisa ser possível para a espécie escolhida
if ($peso !== null && !$v->temErro('especie')) {
    [$nomeEsp, $min, $max] = $especies[campo('especie')];
    if ($peso < $min || $peso > $max) {
        $v->erro('peso', "Para {$nomeEsp} o peso deve ficar entre " . str_replace(".", ",", (string)$min) . " e {$max} kg.");
    }
}

$v->encerrarSeHouverErros();

// ----- Lógica adicional -----
[$nomeEsp, , , $idadeIdoso] = $especies[campo('especie')];
$tempo = $nascimento->diff(new DateTimeImmutable('today'));
$meses = $tempo->y * 12 + $tempo->m;

if ($meses < 12) {
    $fase = 'Filhote';
} elseif ($tempo->y >= $idadeIdoso) {
    $fase = 'Idoso';
} else {
    $fase = 'Adulto';
}

$resumo = [
    'Nome' => campo('nome') . (campo('sexo') === 'M' ? ' (macho)' : ' (fêmea)'),
    'Espécie / raça' => $nomeEsp . ' — ' . campo('raca'),
    'Tutor (CPF)' => formatarCpf(campo('cpf_tutor')),
    'Idade' => "{$tempo->y} ano(s) e {$tempo->m} mês(es)",
    'Fase da vida' => $fase,
];

// Porte só faz sentido para cães (define o preço do banho e tosa)
if (campo('especie') === 'cao') {
    if ($peso <= 10) {
        $porte = 'Pequeno';
    } elseif ($peso <= 25) {
        $porte = 'Médio';
    } else {
        $porte = 'Grande';
    }
    $resumo['Porte'] = $porte . ' (' . number_format($peso, 1, ',', '.') . ' kg)';
} else {
    $resumo['Peso'] = number_format($peso, 2, ',', '.') . ' kg';
}

$alertas = [];
if ($fase === 'Filhote' && in_array(campo('especie'), ['cao', 'gato'], true)) {
    $alertas[] = 'Filhote: confira a carteira de vacinação (V8/V10 ou V4/V5 e antirrábica) antes de banho e tosa.';
}
if ($fase === 'Idoso') {
    $alertas[] = 'Animal idoso: recomende check-up veterinário a cada 6 meses.';
}

responder(200, [
    'ok' => true,
    'mensagem' => 'Animal validado com sucesso!',
    'resumo' => $resumo,
    'alertas' => $alertas,
]);
