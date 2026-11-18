# Projeto técnico — escolha de stack na instalação do ptah: Livewire, React ou Vue

| | |
|---|---|
| Situação | Proposta, aguardando decisão |
| Data | 18/11/2026 |
| Base | ptah 1.58.6 (Laravel 11–13, Livewire 4) |
| Para quem | Mantenedor do ptah e time do ERP que vai migrar de Laravel 10 + Livewire 2 para Laravel 13 + React |

## 0. Atualização de 18/11/2026 — o que mudou depois da primeira versão deste documento

Três decisões, tomadas com medição, substituem partes das seções 4, 9 e da estimativa:

1. **API JSON direta, e não Inertia como contrato.** O BaseCrud é um componente (uma página pode ter dois, um dentro do outro, um numa aba), e o Inertia entrega dados por página. Os componentes React e Vue consomem a API; o Inertia fica como casca opcional para login, layout e navegação. Quem quiser só o BaseCrud usa o componente com a autenticação do próprio projeto.
2. **O núcleo não precisa ser reescrito para a API existir.** Uma sonda mostrou que o BaseCrud roda inteiro fora do ciclo do Livewire. A API usa o próprio BaseCrud como motor (`Ptah\Crud\CrudRuntime`), com a mesma lógica e as mesmas verificações. A extração descrita em 4.1 deixa de ser pré-requisito e passa a ser melhoria interna, sem prazo.
3. **Primeiro incremento entregue** (1.59.0, desligado por padrão): esquema, listagem, abrir, criar, alterar, excluir e restaurar. Ver [HeadlessApi.md](../HeadlessApi.md).

Medição na mesma tela (25 linhas de 5.000, no mesmo processo, sem rede e sem gzip):

| | Livewire | API |
|---|---|---|
| Troca de página | ~70 ms | ~10 ms |
| Resposta | ~440 KB | ~11 KB |
| Consultas ao banco | 8 | 7 |

As consultas são as mesmas. O ganho vem do que a API não faz: o estado que vai e volta, a reidratação e o HTML da tela.

**O que falta, na ordem:** os demais endpoints (searchdropdown, upload, ações em lote e de linha, exportação, importação, preferências de coluna); os componentes React; os componentes Vue; o instalador com `--stack`.

## 1. Resumo

**Dá para fazer, e o caminho é o mesmo do Jetstream e dos starter kits do Laravel:** uma escolha na instalação (`--stack=livewire`, `--stack=react` ou `--stack=vue`), com o mesmo back-end servindo as três interfaces.

React e Vue usam o mesmo Inertia. Por isso todo o lado do servidor é escrito uma vez para os dois; só a camada de telas e componentes é própria de cada um.

O que torna isso viável é que a maior parte do valor do ptah já não depende da interface: o config de tela em JSON, os filtros, as permissões, os hooks, a exportação, os geradores e os comandos. O que impede hoje é que a lógica do BaseCrud mora dentro do componente Livewire. O projeto, portanto, tem duas metades:

1. **Tirar a lógica do BaseCrud de dentro do Livewire** e colocá-la num núcleo sem interface. O stack Livewire continua igual para quem usa; ele só passa a chamar o núcleo.
2. **Construir as interfaces novas** com Inertia, falando com o mesmo núcleo por HTTP: primeiro React (o que o ERP precisa), depois Vue sobre a mesma base.

Decisões já tomadas para este projeto:

- O ERP passa a **usar o ptah** na migração, no lugar do BaseCrud próprio.
- A primeira versão dos stacks novos cobre **BaseCrud, autenticação e menu**. O resto fica para depois (seção 8).
- **React sai primeiro**, porque é o que o ERP vai usar. **Vue vem em seguida**, reaproveitando todo o servidor.

Estimativa grosseira até o stack React: **7 a 8 meses com uma pessoa, 4 a 5 meses com duas** (uma no núcleo PHP, outra no React), com margem de 30%. O stack Vue acrescenta **cerca de 2 a 3 meses**, porque só as telas e os componentes são refeitos. A fase 0 (duas semanas) existe para confirmar ou derrubar essa estimativa antes de comprometer o resto.

## 2. Contexto

- O ptah nasceu da ideia de um ERP que já tem um BaseCrud próprio, em Laravel 10 e Livewire 2.
- Esse ERP vai migrar para Laravel 13 e trocar Livewire por React.
- Se o ptah só existir em Livewire, o ERP não pode adotá-lo, e os dois seguem como bases separadas.
- Com um stack React, o ERP migra para o ptah, e o pacote ganha um segundo público: quem quer low-code em Laravel mas não quer Livewire.

Sobre o modelo "Jetstream": desde o Laravel 12, o caminho oficial são os starter kits (React, Vue e Livewire), e Jetstream e Breeze deixaram de ser a recomendação para projetos novos. O kit React usa Inertia 2, React 19, TypeScript e Tailwind 4. O stack React do ptah deve ser desenhado para encaixar nesse kit. Os detalhes do kit na versão 13 precisam ser conferidos na fase 0.

## 3. Como o ptah está hoje

Medido na 1.58.6:

| Área | Tamanho | Depende de Livewire? |
|---|---|---|
| `src/Livewire` (40 componentes) | 15,3 mil linhas | Sim |
| — dos quais BaseCrud (componente + 16 traits) | 9 mil linhas | Sim, mas ver abaixo |
| — editor visual de config | 1,4 mil linhas | Sim |
| `src/Services` (filtros, config, permissões, exportação, IA) | 10,5 mil linhas | Não |
| `src/Support` | 9 mil linhas | Quase nada |
| `src/Commands` e `src/Generators` | 11,2 mil linhas | Só no que geram |
| Views Blade (91 arquivos) | 17,7 mil linhas | Sim |
| — telas do BaseCrud | 4,1 mil linhas | Sim |
| Componentes `forge-*` | 31 | Blade + Alpine |
| CSS de componentes | 4,2 mil linhas | Não (tokens reutilizáveis) |
| Testes | 3201, em 395 arquivos | 135 arquivos usam `Livewire::test` |

Três observações decidem o desenho:

**3.1. O acoplamento do BaseCrud ao Livewire é raso na API e fundo no estado.** Os 16 traits usam pouco da API do Livewire (33 `dispatch`, 3 `#[Computed]`, 1 `#[On]`, upload de arquivo). O problema é outro: a busca, os filtros, a ordenação, a página, o registro em edição e os erros do formulário são propriedades públicas do componente, e toda a lógica lê e escreve nelas. Extrair significa dar a essa lógica um objeto de estado no lugar do `$this`.

**3.2. As células saem do servidor como HTML.** `formatCell()` devolve uma string HTML pronta para cada um dos 18 renderers (badge, money, link, image, progress…). O React precisa de dados, não de HTML. Esse é o ponto que mais muda no núcleo.

**3.3. A segurança do BaseCrud foi escrita para um componente com estado no cliente.** `#[Locked]`, `scopedQuery()`, `crudConfigAllows()`, colunas negadas e a validação de cada valor vindo do navegador são o resultado de meses de laudos. Nada disso pode ser reescrito do zero no stack React: tem de ser o mesmo código.

## 4. Arquitetura proposta

```
                 ┌──────────────────────────────────────────────┐
                 │  NÚCLEO (sem interface)                      │
                 │  CrudScreen · CrudEngine · CellView · Form   │
                 │  + o que já existe: FilterService,           │
                 │  CrudConfigService, CrudPermission, hooks,   │
                 │  CrudQueryScope, exportação, importação      │
                 └───────────────┬──────────────────┬───────────┘
                                 │                  │
              ┌──────────────────┴───┐      ┌───────┴──────────────────┐
              │ Adaptador Livewire   │      │ Adaptador HTTP (Inertia) │
              │ BaseCrud (o de hoje) │      │ controllers + rotas      │
              │ Blade + Alpine       │      │ React + TypeScript       │
              └──────────────────────┘      └──────────────────────────┘
```

### 4.1. O núcleo

Quatro peças novas, todas PHP puro:

- **`CrudScreen` — a definição da tela, só no servidor.** Reúne o que hoje chega pelo `mount()`: model, tela do config (`configRoute`), `lockedFilters`, `scope` e `scopeParams`, `whereHasFilter`, empresa. É resolvida a cada requisição a partir do servidor, nunca do navegador.
- **`CrudState` — o que o usuário escolheu.** Busca, filtros, ordenação, página, itens por página, lixeira, agrupamento. No Livewire são as propriedades públicas de hoje. No React vêm da URL e do corpo da requisição. Tudo aqui é tratado como entrada não confiável, como já é hoje.
- **`CrudEngine` — as operações.** `list()`, `totals()`, `find()`, `create()`, `update()`, `delete()`, `restore()`, `bulk()`, `export()`, `import()`, `searchDropdown()`. É para onde vai o conteúdo dos traits `HasCrudQuery`, `HasCrudForm`, `HasCrudDeletion`, `HasCrudFilters` e companhia. Cada operação recebe `CrudScreen` + `CrudState` e devolve um resultado tipado (linhas, erros de validação, recusa com motivo).
- **`CellView` — a célula como dado.** Em vez de HTML, cada renderer devolve uma estrutura: `{type: "badge", label, color}`, `{type: "money", value, formatted}`, `{type: "link", href, text, newTab}`. O Blade passa a desenhar a partir dela (mesmo resultado de hoje), e o React também.

O formulário segue a mesma ideia: o núcleo entrega um **esquema do formulário** (campos, tipo, obrigatório, opções, máscara, visibilidade condicional) já filtrado pelas permissões de coluna, e valida no `create()`/`update()` com o `FormValidatorService` que já existe.

### 4.2. O adaptador Livewire

É o `BaseCrud` atual, emagrecido: guarda as propriedades públicas, monta `CrudScreen` e `CrudState` a partir delas e chama o núcleo. **Para quem usa o ptah em Livewire nada muda**: mesmas tags, mesmos parâmetros, mesmos configs, mesmos eventos. Os 3201 testes continuam valendo e são a rede de segurança da extração.

### 4.3. O adaptador HTTP e o React

- **Um adaptador para React e Vue.** As rotas, os controllers e as props da página são os mesmos; o Inertia entrega a página ao React ou ao Vue conforme o stack instalado. A diferença entre os dois fica toda em `resources/js/react` e `resources/js/vue`.
- **Inertia, e não uma API separada com SPA.** Com Inertia, as rotas, a sessão, os middlewares, os guards e as permissões continuam no Laravel, do jeito que o ptah já trata. O React recebe as props da página e navega sem recarregar. Uma API REST pura obrigaria a refazer autenticação (tokens), roteamento no cliente e tratamento de erro, sem ganho para um ERP.
- **Uma tela é registrada no servidor.** O equivalente React de `@livewire('ptah-base-crud', [...])` é uma rota que nomeia a tela:

  ```php
  // routes/web.php
  Ptah::screen('/produtos', Product::class);

  // com recorte travado: resolvido no servidor a cada requisição
  Ptah::screen('/meus-atendimentos', Appointment::class)
      ->lockedFilters(fn () => ['employee_id' => auth()->user()->employee_id]);
  ```

- **O navegador só manda intenções.** Listar (`GET` com busca, filtros, ordem e página na URL), abrir registro, salvar, excluir, ação em lote, exportar. Quem decide o model, a tela do config e o recorte é sempre o `CrudScreen` do servidor. Com isso some uma classe inteira de problemas que o stack Livewire precisa vigiar com `#[Locked]`: não existe estado de servidor guardado no cliente.
- **Props da página:** o config já filtrado (sem colunas negadas, sem chaves internas), as linhas com `CellView`, totais, paginação, permissões da tela (pode criar, editar, excluir, exportar) e as traduções usadas.
- **Estado na URL.** Busca, filtros, ordenação e página ficam na query string. Recarregar, voltar e compartilhar o link funcionam sem código extra. As preferências salvas (colunas visíveis, itens por página) continuam em `user_preferences`.

### 4.4. Onde fica o código React

**Recomendação: dentro do próprio pacote Composer**, em `resources/js`, com um alias do Vite apontando para `vendor/jonytonet/ptah/resources/js`. O build do projeto compila junto.

- Uma versão só: `composer update` atualiza back-end e front-end juntos. O ptah publica correções toda semana, e duas versões para manter sincronizadas (Composer e npm) seriam fonte permanente de erro.
- O instalador acrescenta `react`, `@inertiajs/react` e as demais dependências ao `package.json` do projeto.
- As telas que o projeto quiser personalizar podem ser publicadas (`vendor:publish`), como as views hoje.

A alternativa de copiar o código-fonte para o projeto (modelo shadcn) foi descartada como padrão: cada projeto ficaria com uma cópia que não recebe correção.

## 5. Instalação

```bash
php artisan ptah:install --stack=livewire   # o de hoje; continua sendo o padrão
php artisan ptah:install --stack=react      # novo
php artisan ptah:install --stack=vue        # novo, depois do React
```

- A escolha fica em `config/ptah.php` (`'stack' => 'livewire' | 'react' | 'vue'`).
- `ptah:forge` passa a gerar conforme o stack: no React e no Vue, em vez da view Blade com o componente Livewire, registra a tela (`Ptah::screen`) e o item de menu. Model, migration, service, repository, DTO e config de tela são os mesmos.
- `ptah:config`, `ptah:check`, `ptah:config:doctor`, export/import de configs, MCP e extensão do VS Code não mudam: trabalham sobre o config, que é o mesmo.
- Os dois stacks não se misturam num mesmo projeto na v1 (ver decisão em aberto 12.4).

## 6. Segurança

Regra do projeto: **nenhuma verificação é reescrita; todas mudam de lugar junto com o código.**

- Tudo o que hoje protege `scopedQuery()`, `recordInScope()`, `crudConfigAllows()`, `authorizeCrudAction()`, colunas negadas, `SqlIdentifier`, validação de `select` e de `searchdropdown` vai para o núcleo e atende os dois adaptadores.
- Os testes de segurança existentes (IDOR, coluna negada, valor forjado, operador forjado) ganham uma segunda execução pelo adaptador HTTP. O mesmo cenário tem de ser recusado nos dois.
- Pontos novos do stack React que precisam de desenho próprio:
  - **HTML vindo do servidor.** `colsMetodoCustom` com `colsMetodoRaw` devolve HTML do desenvolvedor. No React isso só entra por um tipo de célula explícito (`html`), nunca por padrão.
  - **Ações de linha do tipo `livewire`.** Não existem no React. Viram ações de servidor que implementam um contrato, no molde do `BulkActionHandler`.
  - **Props da página são visíveis no navegador.** O filtro do config (colunas negadas, hooks, nomes de classe) é obrigatório e tem teste próprio.

## 7. Testes

- **Núcleo:** testes diretos do `CrudEngine`, sem Livewire. São os mais rápidos e passam a ser a referência.
- **Paridade:** um conjunto de cenários que roda o mesmo caso pelos dois adaptadores e compara o resultado (linhas, totais, erros, recusas).
- **Livewire:** os testes atuais, intactos.
- **React:** testes de componente (Vitest + Testing Library) e os fluxos principais em navegador, com o Dusk que o projeto já usa.
- **CI:** a matriz atual (PHP 8.2–8.4, Laravel 11–13) ganha um job de build e teste do front.

## 8. Escopo

### Entra na v1 dos stacks React e Vue

- Listagem: colunas, 18 renderers, ordenação, paginação, busca geral, filtros, filtros rápidos de data, totalizadores, agrupamento, estilo condicional de linha, lixeira.
- Formulário: os 18 tipos de campo, máscaras, validação, searchdropdown, upload de imagem e arquivo, campos condicionais, hooks.
- Exclusão, restauração, ações em lote, ações de linha.
- Exportação (Excel e PDF, síncrona e em fila) e impressão. Os controllers já são HTTP.
- Permissões (driver `ptah` e driver `gate`), permissão por coluna, multiempresa.
- Autenticação: login, 2FA, recuperação de senha, perfil.
- Layout: menu lateral, barra superior, tema claro e escuro, toasts.
- Instalador, gerador e documentação.

### Fica em Livewire ou para a v2

- Editor visual de config (1,4 mil linhas, a tela mais complexa do pacote). Na v1, quem está no stack React configura por CLI, JSON ou MCP. É a pendência mais sentida e a primeira candidata à v2.
- Chat de IA e configuração de provedores.
- Kanban, calendário e cards.
- Telas administrativas: empresas, departamentos, papéis, páginas, auditoria, menu, configurações.
- Importação por planilha, histórico, anexos, rascunho de formulário, notificações.

## 9. Fases

| Fase | Entrega | Estimativa |
|---|---|---|
| **0. Prova** | Uma listagem somente leitura em React, servida pelo caminho de consulta extraído. Confirma o desenho e a estimativa. | 2 semanas |
| **1. Núcleo** | `CrudScreen`, `CrudState`, `CrudEngine`. O BaseCrud Livewire passa a chamar o núcleo. Sai em versões 1.x, sem mudança para quem usa. | 6 a 8 semanas |
| **2. Dados em vez de HTML** | `CellView` e esquema de formulário. O Blade desenha a partir deles. | 3 a 4 semanas |
| **3. Adaptador HTTP** | Rotas, controllers, props da página, tipos TypeScript gerados a partir do esquema do config. | 3 semanas |
| **4. React: componentes e tela de CRUD** | Componentes de base, tabela, filtros, formulário com todos os tipos, searchdropdown, máscaras, exportação. | 8 a 10 semanas |
| **5. Autenticação, layout e menu** | | 3 semanas |
| **6. Instalador, gerador, docs, MCP** | `--stack=react`, `ptah:forge`, guia de instalação. | 2 a 3 semanas |
| **7. Piloto no ERP** | Um módulo do ERP rodando no stack React. | 4 semanas ou mais |
| **8. Stack Vue** | As mesmas telas e componentes em Vue 3, sobre o adaptador HTTP já pronto. Os testes de paridade passam a rodar nos três stacks. | 8 a 10 semanas |

- As fases 1 e 2 valem por si, mesmo que o React nunca saísse: são o trabalho que o [Roadmap](../Roadmap.md) já pede antes da 2.0 (núcleo estável, API pública marcada, `_modal-form` quebrado em partes).
- As fases 4 e 5 podem correr em paralelo com 2 e 3 se houver uma segunda pessoa.
- **Proposta de versão:** fases 1 e 2 em versões 1.x; o stack React estreia na **2.0**, junto com a API pública estável.

## 10. Migração do ERP

O ERP está em Laravel 10 com Livewire 2. O Livewire 2 não acompanha o Laravel 13, então **não há como migrar tela a tela dentro da mesma aplicação**. O caminho proposto:

1. **Aplicação nova** em Laravel 13 com o ptah no stack React, apontando para o mesmo banco.
2. **Mesma sessão ou login único** entre as duas aplicações, para o usuário não perceber a troca.
3. **Migração por módulo:** um proxy (ou as próprias rotas) manda cada módulo migrado para a aplicação nova. O resto continua na antiga até chegar a vez.
4. **Conversor de configs:** um comando que lê o config de tela do BaseCrud do ERP e escreve no formato do ptah. Como o ptah descende desse formato, a expectativa é de conversão majoritariamente mecânica, com um relatório do que não tem equivalente.
5. **Código próprio das telas** (componentes que estendem o BaseCrud do ERP, métodos customizados) vira o que o ptah já oferece para isso: hooks, `CrudQueryScope`, `BulkActionHandler`, `colsMetodoCustom`.

O que precisa ser levantado no ERP antes de estimar esta parte:

- quantas telas e quantos configs existem;
- quais chaves de config o ERP usa que o ptah não tem;
- quantas telas têm código próprio além do config;
- como o ERP trata permissão e multiempresa hoje, e se casa com os módulos do ptah.

## 11. Riscos

| Risco | Efeito | Como reduzir |
|---|---|---|
| A extração do núcleo quebra o stack Livewire em produção (B2B, JTEM, Cher, PetPlace) | Alto | Extrair um trait por vez, cada um numa versão, com a suíte completa e os testes de navegador. Nenhuma mudança de comportamento junto. |
| Manter três interfaces multiplica o custo de cada recurso novo de tela | Alto, permanente | Regra: recurso novo entra primeiro no núcleo. O que for só de interface pode sair num stack antes dos outros, com uma tabela de paridade publicada na documentação. Só abrir o Vue depois que o React estiver estável. |
| Diferença de comportamento entre os dois stacks | Médio | Testes de paridade (seção 7). |
| A v1 sem editor visual frustra quem vem do Livewire | Médio | Deixar explícito; priorizar o editor na v2. |
| Falta de experiência com React na manutenção do pacote | Médio | Decidir quem mantém o front antes da fase 4 (decisão em aberto 12.2). |
| Formato de config do ERP mais distante do ptah do que se supõe | Médio | Levantamento da seção 10 antes da fase 7. |
| A estimativa da fase 4 estoura | Médio | A fase 0 mede uma tela real; reestimar ao fim dela. |

## 12. Decisões em aberto

1. **Biblioteca de componentes no React.** Usar shadcn/ui (o que o starter kit do Laravel usa, mais rápido de construir) ou portar os componentes `forge-*` para manter a mesma aparência nos dois stacks. Recomendação: shadcn/ui com os tokens de cor do ptah.
2. **Quem desenvolve e mantém o front React.** O mantenedor do ptah, alguém do time do ERP, ou os dois.
3. **Versão.** Confirmar o stack React como marco da 2.0.
4. **Stacks misturados.** Permitir Livewire e React no mesmo projeto ajudaria uma migração gradual de quem já usa o ptah. Fica fora da v1; decidir se entra depois.
5. **TypeScript.** Recomendação: sim, com os tipos do config gerados a partir do JSON Schema que o pacote já tem.
6. **Laravel mínimo nos stacks React e Vue.** Recomendação: 12 ou mais novo, alinhado aos starter kits.
7. **Quando abrir o Vue.** Recomendação: só depois do piloto do ERP em React, para não manter três interfaces enquanto o desenho ainda muda. Se houver demanda concreta por Vue antes disso, as fases 4 e 8 podem correr em paralelo com mais uma pessoa.

## 13. Alternativas descartadas

- **Reescrever o ptah em React e abandonar o Livewire.** Quatro sistemas em produção dependem do stack atual.
- **Um pacote separado (`ptah-react`) com cópia do back-end.** Duas bases de regras de segurança para manter iguais; é exatamente a situação que este projeto quer evitar com o ERP.
- **API REST + SPA sem Inertia.** Refaz autenticação, rotas e tratamento de erro sem ganho para este caso.
- **React dentro do Livewire** (componentes React montados em views Blade). Mantém a dependência do Livewire, que é o que o ERP quer deixar.

## 14. Próximo passo

Aprovar a **fase 0**: duas semanas para extrair o caminho de consulta do `HasCrudQuery` para o núcleo e servir uma listagem real em React. Ao fim dela, este documento é revisado com números medidos, e a decisão de seguir é tomada com base neles.
