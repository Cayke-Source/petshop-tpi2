<?php
/*
 * Agendamento de Serviços (tabela futura: agendamentos — liga tutores,
 * animais, servicos e funcionarios)
 * Lógica adicional: calcula o preço pelo serviço e porte do animal, soma a
 * taxa de leva-e-traz, calcula o horário de término e recusa horários fora
 * do expediente (seg–sex 8h–18h, sáb 8h–13h, domingo fechado) ou no passado.
 */

declare(strict_types=1);
require __DIR__ . '/validacao.php';

exigirPost();
$v = new Validador();

const TAXA_LEVA_E_TRAZ = 15.00;
// serviço => [nome exibido, preço base (porte pequeno), duração em minutos]
$servicos = [
    'banho'       => ['Banho', 45.00, 60],
    'tosa'        => ['Tosa', 55.00, 60],
    'banho_tosa'  => ['Banho e tosa', 85.00, 120],
    'hidratacao'  => ['Hidratação de pelos', 40.00, 30],
    'consulta'    => ['Consulta veterinária', 120.00, 30],
    'vacina'      => ['Aplicação de vacina', 90.00, 15],
];
// porte => [nome, multiplicador do preço]
$portes = [
    'pequeno' => ['Pequeno', 1.0],
    'medio'   => ['Médio', 1.3],
    'grande'  => ['Grande', 1.6],
];

if ($v->obrigatorio('cpf_tutor', 'CPF do tutor') && !cpfValido(campo('cpf_tutor'))) {
    $v->erro('cpf_tutor', 'CPF do tutor inválido.');
}

if ($v->obrigatorio('nome_animal', 'Nome do animal')) {
    $v->tamanho('nome_animal', 'Nome do animal', 2, 40);
}

if ($v->obrigatorio('servico', 'Serviço')) {
    $v->opcao('servico', 'Serviço', array_keys($servicos));
}

if ($v->obrigatorio('porte', 'Porte')) {
    $v->opcao('porte', 'Porte', array_keys($portes));
}

$data = null;
if ($v->obrigatorio('data', 'Data')) {
    $data = $v->data('data', 'Data');
}

$horario = campo('horario');
if ($v->obrigatorio('horario', 'Horário') && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horario)) {
    $v->erro('horario', 'Horário inválido (use HH:MM).');
}

if (mb_strlen(campo('observacoes')) > 300) {
    $v->erro('observacoes', 'Observações com no máximo 300 caracteres.');
}

$v->encerrarSeHouverErros();

// ----- Lógica adicional: expediente -----
[$nomeServico, $precoBase, $duracao] = $servicos[campo('servico')];
[$nomePorte, $multiplicador] = $portes[campo('porte')];

$inicio = DateTimeImmutable::createFromFormat('Y-m-d H:i', campo('data') . ' ' . $horario);
$fim = $inicio->modify("+{$duracao} minutes");
$diaSemana = (int)$inicio->format('N'); // 1 = segunda ... 7 = domingo

if ($inicio < new DateTimeImmutable('now')) {
    $v->erro('data', 'Não é possível agendar em uma data/horário que já passou.');
} elseif ($diaSemana === 7) {
    $v->erro('data', 'O pet shop não abre aos domingos.');
} else {
    $fechamento = $diaSemana === 6 ? '13:00' : '18:00';
    if ($inicio->format('H:i') < '08:00' || $fim->format('H:i') > $fechamento) {
        $v->erro('horario', "Neste dia o atendimento é das 08:00 às {$fechamento}. "
            . "O serviço dura {$duracao} min e terminaria às " . $fim->format('H:i') . '.');
    }
}

$v->encerrarSeHouverErros();

// ----- Lógica adicional: preço -----
// consulta e vacina não variam com o porte
$preco = in_array(campo('servico'), ['consulta', 'vacina'], true)
    ? $precoBase
    : $precoBase * $multiplicador;

$levaETraz = isset($_POST['leva_e_traz']);
if ($levaETraz) {
    $preco += TAXA_LEVA_E_TRAZ;
}

$nomesDias = [1 => 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
$alertas = [];
if (campo('servico') === 'vacina' || campo('servico') === 'consulta') {
    $alertas[] = 'Peça ao tutor para trazer a carteira de vacinação do animal.';
}
if ($levaETraz) {
    $buscar = $inicio->modify('-30 minutes')->format('H:i');
    $alertas[] = "Leva-e-traz: buscar " . campo('nome_animal') . " por volta das {$buscar}.";
}

responder(200, [
    'ok' => true,
    'mensagem' => 'Agendamento validado com sucesso!',
    'resumo' => [
        'Animal' => campo('nome_animal') . " (porte {$nomePorte})",
        'Tutor (CPF)' => formatarCpf(campo('cpf_tutor')),
        'Serviço' => $nomeServico,
        'Quando' => $inicio->format('d/m/Y') . ' (' . $nomesDias[$diaSemana] . '), das '
            . $inicio->format('H:i') . ' às ' . $fim->format('H:i'),
        'Leva-e-traz' => $levaETraz ? 'Sim (+' . dinheiro(TAXA_LEVA_E_TRAZ) . ')' : 'Não',
        'Valor total' => dinheiro($preco),
    ],
    'alertas' => $alertas,
]);
