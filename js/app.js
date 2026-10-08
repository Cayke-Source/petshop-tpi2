/*
 * PetVida — script compartilhado por todos os formulários.
 *
 * Cada <form> do sistema possui o atributo data-api com o endereço do
 * script PHP que vai receber os dados. Este arquivo intercepta o envio,
 * manda os dados com fetch() (método POST) e mostra a resposta JSON do
 * servidor na própria página, sem recarregar.
 */

document.addEventListener('DOMContentLoaded', function () {
  const formularios = document.querySelectorAll('form[data-api]');
  formularios.forEach(function (form) {
    form.addEventListener('submit', function (evento) {
      evento.preventDefault(); // impede o envio tradicional (recarregar a página)
      enviarFormulario(form);
    });
    form.addEventListener('reset', function () {
      limparErros(form);
      esconderResultado(form);
    });
  });
});

/** Envia o formulário para o PHP usando fetch e trata a resposta. */
async function enviarFormulario(form) {
  const botao = form.querySelector('button[type="submit"]');
  limparErros(form);
  botao.disabled = true;
  botao.textContent = 'Enviando...';

  try {
    const dados = new FormData(form); // pega todos os campos pelo atributo name
    const resposta = await fetch(form.dataset.api, {
      method: 'POST',
      body: dados
    });

    const json = await resposta.json();

    if (json.ok) {
      mostrarSucesso(form, json);
    } else {
      mostrarErros(form, json);
    }
  } catch (erro) {
    mostrarResultado(form, 'erro',
      '<h3>Não foi possível falar com o servidor</h3>' +
      '<p>Verifique se o servidor PHP está rodando (php -S localhost:8000).</p>');
    console.error(erro);
  } finally {
    botao.disabled = false;
    botao.textContent = 'Enviar';
  }
}

/** Mostra a mensagem de sucesso com o resumo calculado pelo servidor. */
function mostrarSucesso(form, json) {
  let html = '<h3>' + escapar(json.mensagem) + '</h3>';

  if (json.resumo) {
    html += '<ul>';
    for (const rotulo in json.resumo) {
      html += '<li><strong>' + escapar(rotulo) + ':</strong> ' + escapar(String(json.resumo[rotulo])) + '</li>';
    }
    html += '</ul>';
  }

  if (json.alertas && json.alertas.length > 0) {
    html += '<div class="alertas"><strong>Atenção:</strong><ul>';
    json.alertas.forEach(function (alerta) {
      html += '<li>' + escapar(alerta) + '</li>';
    });
    html += '</ul></div>';
  }

  mostrarResultado(form, 'ok', html);
}

/** Marca em vermelho cada campo que o servidor recusou. */
function mostrarErros(form, json) {
  const erros = json.erros || {};
  for (const nomeCampo in erros) {
    const entrada = form.querySelector('[name="' + nomeCampo + '"]');
    if (!entrada) continue;
    const caixa = entrada.closest('.campo');
    caixa.classList.add('invalido');
    const msg = caixa.querySelector('.msg-erro');
    if (msg) msg.textContent = erros[nomeCampo];
  }

  const primeiro = form.querySelector('.campo.invalido input, .campo.invalido select, .campo.invalido textarea');
  if (primeiro) primeiro.focus();

  mostrarResultado(form, 'erro',
    '<h3>' + escapar(json.mensagem || 'Corrija os campos destacados.') + '</h3>');
}

function limparErros(form) {
  form.querySelectorAll('.campo.invalido').forEach(function (c) { c.classList.remove('invalido'); });
  form.querySelectorAll('.msg-erro').forEach(function (m) { m.textContent = ''; });
}

function mostrarResultado(form, tipo, html) {
  const caixa = document.getElementById(form.dataset.resultado);
  caixa.className = 'resultado visivel ' + tipo;
  caixa.innerHTML = html;
  caixa.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function esconderResultado(form) {
  const caixa = document.getElementById(form.dataset.resultado);
  caixa.className = 'resultado';
  caixa.innerHTML = '';
}

/** Evita que textos vindos do servidor sejam interpretados como HTML. */
function escapar(texto) {
  const div = document.createElement('div');
  div.textContent = texto;
  return div.innerHTML;
}
