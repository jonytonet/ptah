# Projeto técnico — escolha de CSS na instalação do ptah: Tailwind ou Bootstrap

| | |
|---|---|
| Situação | Proposta. Nada foi implementado; nenhuma decisão de início tomada |
| Data | 19/11/2026 |
| Base | ptah 1.59.1 (Laravel 11–13, Livewire 4, Tailwind v4) |
| Bootstrap alvo | **5.3.9** (estável). A 6.0 só existe como `6.0.0-alpha.1`, de 08/10/2026 |
| Para quem | Mantenedor do ptah e time do projeto em Bootstrap que vai usar o BaseCrud sem converter para Tailwind |

## 1. Resumo

Hoje o ptah exige Tailwind no projeto que o usa: as telas são escritas com
classes utilitárias do Tailwind e o instalador injeta `@import 'tailwindcss'`
no `app.css`. Um projeto em Bootstrap não consegue usar o BaseCrud sem
instalar o Tailwind ao lado, e os dois entram em conflito.

A proposta é o instalador perguntar qual CSS o projeto usa
(`ptah:install --css=tailwind|bootstrap`) e o ptah se adaptar:

- **Tailwind**: é o padrão. `ptah:install` sem a opção instala com Tailwind,
  como é hoje, e nada muda para quem já usa. Bootstrap só entra quando o
  projeto opta por ele.
- **Bootstrap**: o projeto não instala Tailwind. O ptah entrega o próprio CSS
  já compilado e isolado, e pega cores, fontes, raio e modo escuro do tema
  Bootstrap do projeto.

O caminho recomendado é **um conjunto de telas com duas "peles" de CSS**, e
não um segundo conjunto de telas escritas em Bootstrap. Duplicar as telas
dobraria o custo de cada correção, para sempre.

A entrega é em duas etapas. A primeira atende o caso concreto que motivou o
pedido (só o BaseCrud, em Livewire, dentro do layout Bootstrap do projeto) em
cerca de três semanas. A segunda estende a opção ao resto do pacote.

## 2. O que foi medido no pacote

| Parte | Tamanho | Depende de Tailwind? |
|---|---|---|
| Telas Livewire (Blade) | 93 views, 3.128 atributos `class`, ~5.700 utilitários, 415 variantes (`md:`, `dark:`, `hover:`) | Sim, fortemente |
| Só o BaseCrud | 21 views, 1.608 atributos `class`: 542 na tela e 1.066 no editor de configuração (a engrenagem) | Sim |
| Kit `<x-forge-*>` | 31 componentes; o BaseCrud usa 8 deles | Sim |
| `ptah-components.css` | 4.174 linhas, 225 classes semânticas `ptah-c-*` | Pouco (5 diretivas) |
| Tokens de tema | 55 variáveis `--ptah-*` | Não |
| Stacks React e Vue (`ptah-ui.css`) | 2.069 linhas, prefixos `pc-`/`pl-` | Não; só as células renderizadas pelo servidor usam classes Tailwind |
| Instalador | injeta `@import 'tailwindcss'` e os tokens no `app.css` | Sim |

### 2.1 Onde os dois frameworks colidem

Classes das nossas views que também existem no Bootstrap, com outro
significado:

| Classe | Ocorrências | Problema |
|---|---|---|
| `px-3`, `px-4`, `py-3`, `p-3`, `p-4`, `p-5`, `mt-*`, `mb-*`, `gap-3`, `gap-4`… | ~800 | Escalas diferentes: `p-4` é 1rem no Tailwind e 1,5rem no Bootstrap; `p-5` é 1,25rem contra 3rem |
| `border` | 225 | Bootstrap impõe cor e espessura próprias |
| `rounded` | 174 | Raio diferente |
| `text-primary`, `bg-primary`, `text-danger`… | ~165 | Bootstrap troca pela cor dele |

O agravante: **os utilitários do Bootstrap levam `!important`**. Numa página
que carregue os dois, o Bootstrap vence, e as telas do ptah ficam com
espaçamento e bordas errados. Este é o problema central.

Outros dois pontos:

- **Camadas (`z-index`)**: nossos modais e menus usam de 40 a 60. No
  Bootstrap a barra fixa usa 1030 e o modal 1055. Dentro de um layout
  Bootstrap, um modal do ptah ficaria por baixo da barra.
- **Reset**: o reset do Tailwind (preflight) e o do Bootstrap (reboot) não
  são iguais. Carregar o do Tailwind na página toda altera a aparência das
  telas do próprio projeto.

### 2.2 O que o BaseCrud precisa e hoje só o nosso layout entrega

Fora do `forge-dashboard-layout`, o BaseCrud perde: as cores do tema
(`partials.theme-colors`), o bloco de estilos do modo escuro, a regra do
`x-cloak`, o host de avisos (inclusive o "desfazer" da exclusão), os ícones
Boxicons e o script de aparência. Não existe hoje um jeito oficial de usar o
BaseCrud num layout que não seja o do ptah. Isso precisa existir de qualquer
forma, com ou sem Bootstrap.

## 3. Alternativas consideradas

| Alternativa | Como seria | Por que sim / por que não |
|---|---|---|
| A. Segundo conjunto de views em Bootstrap | 93 views reescritas com marcação Bootstrap | Aparência 100% nativa, mas toda correção passa a ser feita duas vezes. **Descartada.** |
| B. CSS do ptah compilado e isolado | Mesmas views; o ptah entrega o Tailwind já compilado, restrito a um escopo | Funciona rápido e sem tocar nas views. Aparência continua a do ptah. **É a Etapa 1.** |
| C. Ponte de tokens | Os 55 `--ptah-*` passam a valer as variáveis `--bs-*` do projeto | Cores, fonte, raio e modo escuro seguem o tema do projeto. Barata. **Entra junto da B.** |
| D. Camada semântica | Views deixam de usar utilitários e passam a usar só classes `ptah-c-*`; cada pele é um arquivo CSS | É o destino correto e casa com o roadmap da 2.0, mas é o trabalho maior. **Etapa 2, por tela.** |

## 4. Como fica para quem usa

```bash
php artisan ptah:install --css=bootstrap
```

- Grava `PTAH_CSS=bootstrap` no `.env` (`ptah.css.framework`).
- **Não** toca no `app.css`, no `vite.config.js` nem no `package.json`.
- Publica `public/vendor/ptah/ptah.css` (o CSS do pacote, compilado).

No layout do projeto, uma linha:

```blade
<head>
    …o Bootstrap do projeto…
    <x-ptah-assets />
</head>
```

E a tela:

```blade
<div class="container">
    @livewire('ptah-base-crud', ['model' => \App\Models\Cliente::class])
</div>
```

Trocar a cor primária ou ligar `data-bs-theme="dark"` no projeto muda o
BaseCrud junto, sem recompilar nada.

## 5. Etapa 1 — BaseCrud dentro de um projeto Bootstrap (cerca de 3 semanas)

Escopo: só o BaseCrud, em Livewire, dentro do layout do projeto. Ficam fora o
layout do ptah, as telas administrativas, o kit `<x-forge-*>` em Bootstrap e
as stacks React e Vue.

### Fase 0 — Protótipo de isolamento (2 a 3 dias)

É a fase que pode mudar o plano; por isso vem antes de tudo.

- Montar uma página Bootstrap 5.3.9 real com um BaseCrud dentro.
- Escolher a forma de isolar o CSS do ptah:
  - compilar o nosso Tailwind restrito a `.ptah-scope` e com `!important`
    (a verificar se o Tailwind v4 aceita o escopo direto; se não, um passo de
    PostCSS no nosso build);
  - prefixar as classes exigiria alterar todas as views: só se a primeira
    forma falhar.
- Medir o peso do `ptah.css` compilado.
- Conferir se a configuração de permissões por tela basta para um projeto
  que não usa os módulos do ptah (auth, permissões, empresa).
- **Critério de saída**: tabela, barra, filtros e modal iguais ao original
  dentro da página Bootstrap, e o resto da página do projeto intacto.

### Fase 1 — BaseCrud embutível (cerca de 1 semana)

- **Peça de montagem** `<x-ptah-assets />` (e `@ptahAssets`): entrega ao
  layout do projeto o que hoje só o nosso layout entrega (item 2.2).
- **CSS compilado e isolado**: `ptah.css` versionado no pacote, gerado por
  `npm run build:css`, com um teste que falha quando o arquivo está
  desatualizado em relação às views.
- **Reset** do Tailwind restrito a `.ptah-scope`.
- **Configuração**: `ptah.css.framework` (`tailwind` padrão, `bootstrap`) e
  `PTAH_CSS`. Lida com valor padrão, para alcançar um `config/ptah.php`
  publicado antes da chave.
- **Instalador**: `--css=bootstrap` conforme o item 4.
- **Camadas**: no modo Bootstrap, modais, menus, avisos e o overlay de
  carregando do ptah ficam acima de 1055.
- **Aceite**: a suíte de navegador do BaseCrud passa dentro da página
  Bootstrap; nenhuma regra do `ptah.css` vale fora do escopo.

### Fase 2 — Cores e tipografia do projeto (3 a 4 dias)

Arquivo `ptah-bootstrap.css` que redefine os tokens a partir do Bootstrap:

| Token ptah | Variável Bootstrap |
|---|---|
| `--ptah-canvas`, `--ptah-surface`, `--ptah-field` | `--bs-body-bg` |
| `--ptah-surface-raised`, `--ptah-panel`, `--ptah-surface-sunken` | `--bs-secondary-bg`, `--bs-tertiary-bg` |
| `--ptah-text`, `--ptah-text-strong` | `--bs-body-color`, `--bs-emphasis-color` |
| `--ptah-text-muted`, `--ptah-text-secondary`, `--ptah-text-faint` | `--bs-secondary-color`, `--bs-tertiary-color` |
| `--ptah-line`, `--ptah-line-field`, `--ptah-line-control` | `--bs-border-color` |
| `--ptah-primary` | `--bs-primary` |
| `--ptah-primary-lite`, `-soft`, `-softer` | `--bs-primary-bg-subtle` |
| `--ptah-primary-border` | `--bs-primary-border-subtle` |
| `--ptah-primary-strong`, `-deep` | `--bs-primary-text-emphasis` |
| `--ptah-primary-ring` | `rgba(var(--bs-primary-rgb), .25)` |
| `--ptah-danger-*`, `--ptah-success-*`, `--ptah-warn-*` | `--bs-danger`, `--bs-success`, `--bs-warning` e os respectivos `-bg-subtle` e `-text-emphasis` |
| raio, fonte, tamanho de fonte | `--bs-border-radius`, `--bs-body-font-family`, `--bs-body-font-size` |

- **Modo escuro**: os tokens escuros respondem a `[data-bs-theme="dark"]`,
  além de `.ptah-dark`.
- **Aceite**: mudar a cor primária e o tema no Bootstrap do projeto muda o
  BaseCrud sem rebuild; contraste AA conferido nas duas peles, nos dois
  temas.

### Fase 3 — Testes e documentação (3 dias, em paralelo)

- CI em matriz: a suíte de navegador do BaseCrud roda nas duas peles.
- Linha de base de CSS (o teste que já existe para o layout) também para o
  `ptah.css`.
- `docs/Css.md` com o passo a passo de um projeto Bootstrap.
- `ptah:check` confere que o `ptah.css` publicado é o da versão instalada;
  `ptah:upgrade-check` avisa quando não é.

## 6. Etapa 2 — a opção vale para o pacote inteiro (por tela, sem data)

Só começa se a Etapa 1 estiver em uso e mostrar que vale a pena.

### Fase 4 — Camada semântica nas views (3 a 5 semanas, por tela)

- Trocar os utilitários Tailwind das views por classes `ptah-c-*` (já
  existem 225), na ordem: tabela, barra de ferramentas, modal de formulário,
  filtros, cards, kanban, calendário; depois o editor de configuração e as
  telas administrativas.
- Cada tela migrada deixa de depender do CSS compilado da Etapa 1. Ao fim,
  cada pele é um arquivo CSS e o escopo com `!important` sai.
- A linha de base de CSS garante "zero mudança visual" na pele Tailwind.
- Sem quebra para quem usa Tailwind: dá para entregar uma tela por versão.
- Casa com o roadmap da 2.0, que já prevê quebrar o `_modal-form`.

### Fase 5 — Layout e telas administrativas (1 a 2 semanas)

- O `forge-dashboard-layout` (barra lateral, barra do topo, sino, seletor de
  empresa) e as telas de empresa, permissões, menu e configurações passam a
  funcionar na pele Bootstrap.

### Fase 6 — Kit `<x-forge-*>` em Bootstrap (2 a 3 semanas, opcional)

- Variante dos 31 componentes com marcação Bootstrap nativa (`btn`,
  `form-control`, `modal`, `alert`), escolhida pela mesma chave. Só vale se
  os projetos em Bootstrap forem montar telas próprias com o kit.

### Fase 7 — Geração e stacks React/Vue (1 semana)

- `ptah:forge` gera as views conforme `ptah.css.framework`.
- React e Vue: a ponte de tokens da Fase 2 aplicada ao `ptah-ui.css`, mais as
  classes das células vindas do servidor.

## 7. Riscos

| Risco | Efeito | Como reduzir |
|---|---|---|
| O isolamento por escopo não funcionar bem no Tailwind v4 | Muda a Fase 1 (passo de PostCSS, ou prefixo nas views) | Fase 0 antes de qualquer outra coisa |
| Peso do `ptah.css` somado ao Bootstrap | Página mais pesada | Medir na Fase 0; a Fase 4 elimina o problema |
| Dois sistemas de modal na mesma página | Foco, rolagem do fundo e camadas em conflito | Teste de navegador específico na Fase 1 |
| Bootstrap 6 sair durante o projeto | Nomes das variáveis `--bs-*` podem mudar | Só a ponte de tokens (Fase 2) depende deles |
| CSS compilado ficar desatualizado em relação às views | Tela sem estilo após uma alteração | Teste que falha quando o build não foi refeito |

## 8. Decisões em aberto

1. **Aparência por usuário** (tema, densidade, tamanho de fonte do ptah): no
   modo Bootstrap continua disponível ou o tema do projeto manda sozinho?
   Recomendação: o tema do projeto manda; fica só a densidade.
2. **Permissões** num projeto que não usa os módulos do ptah: confirmar na
   Fase 0 se a configuração por tela é suficiente.
3. **Etapa 2**: fazer inteira, só a Fase 4, ou parar na Etapa 1.

## 9. Estimativa

| Etapa | Fases | Prazo |
|---|---|---|
| 1 — BaseCrud em projeto Bootstrap | 0, 1, 2, 3 | cerca de 3 semanas |
| 2 — pacote inteiro | 4, 5, 6 (opcional), 7 | 5 a 11 semanas, entregável por tela |

São estimativas feitas sem protótipo. A Fase 0 confirma ou corrige a da
Etapa 1.
