<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Form draft (1.42.0), end to end in a real browser: the draft lives in
 * localStorage, so only a browser can prove that "New" never sees an edit's
 * values, that an edit keeps its own record's draft, and that the buttons
 * and the saved draft behave.
 */
class FormDraftBrowserTest extends DuskTestCase
{
    private const NAME = 'input[wire\:model="formData.name"]';

    private function waitForDraft(Browser $b, string $suffix): void
    {
        $b->waitUntil('(window.__ptahDraftKey() || "").endsWith('.json_encode($suffix).')', 30)->pause(500);
    }

    private function openNew(Browser $b): void
    {
        $b->script('window.__ptahDraftReset()');
        $b->press(__('ptah::ui.btn_new'))->waitFor(self::NAME);
        $this->waitForDraft($b, ':new');
    }

    private function openEdit(Browser $b, int $id): void
    {
        $b->script('window.__ptahDraftReset()');
        $b->click('button[wire\:click="openEdit('.$id.')"]')->waitFor(self::NAME);
        $this->waitForDraft($b, ':edit:'.$id);
    }

    private function close(Browser $b): void
    {
        $b->press(__('ptah::ui.btn_cancel'))->waitUntilMissing(self::NAME, 30)->pause(300);
    }

    private function typeName(Browser $b, string $value): void
    {
        // 400 ms de debounce antes de gravar o rascunho.
        $b->clear(self::NAME)->type(self::NAME, $value)->pause(900);
    }

    private function nameIs(Browser $b, string $value): void
    {
        $b->waitUntil('window.__ptahVal("name") === '.json_encode($value), 30);
    }

    #[Test]
    public function new_and_edit_keep_separate_drafts_and_the_buttons_work(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1280, 900)->visit('/dusk-test/draft')->waitForText('Bia')
                ->script('localStorage.clear()');

            // 1. Novo: o que foi digitado volta ao reabrir, sem aviso de descarte.
            $this->openNew($b);
            $this->typeName($b, 'Rascunho do novo');
            $this->close($b);
            $this->openNew($b);
            $this->nameIs($b, 'Rascunho do novo');
            $b->assertSee(__('ptah::ui.btn_form_draft_clear'));
            $this->close($b);

            // 2. Editar o 1 traz o registro, nunca o rascunho do Novo.
            $this->openEdit($b, 1);
            $this->nameIs($b, 'Ana');
            $this->typeName($b, 'Ana editada');
            $this->close($b);

            // 3. Outro id usa o proprio registro.
            $this->openEdit($b, 2);
            $this->nameIs($b, 'Bia');
            $this->close($b);

            // 4. De volta ao 1: o rascunho dele, e "Voltar ao original" desfaz.
            $this->openEdit($b, 1);
            $this->nameIs($b, 'Ana editada');
            $b->press(__('ptah::ui.btn_form_draft_revert'));
            $this->nameIs($b, 'Ana');
            $this->close($b);

            // 5. O Novo continua com o dele, e "Limpar tudo" o apaga.
            $this->openNew($b);
            $this->nameIs($b, 'Rascunho do novo');
            $b->press(__('ptah::ui.btn_form_draft_clear'));
            $this->nameIs($b, '');
            $this->close($b);
            $this->openNew($b);
            $this->nameIs($b, '');

            // 6. Salvar com sucesso apaga o rascunho do Novo.
            $this->typeName($b, 'Vai ser salvo');
            $b->press(__('ptah::ui.btn_create'))->waitUntilMissing(self::NAME, 30)->pause(500);
            $this->assertNull($b->script("return Object.keys(localStorage).find(k => k.endsWith(':new')) || null;")[0], 'O rascunho sobreviveu ao salvar.');
        });
    }

    #[Test]
    public function the_logout_form_clears_every_draft(): void
    {
        $this->browse(function (Browser $b) {
            $b->visit('/dusk-test/draft')->waitForText('Bia')
                ->script("localStorage.setItem('ptah:draft:x:y:new', '{}'); localStorage.setItem('outra-chave', '1');");

            // O mesmo onsubmit do formulario de logout da navbar/sidebar.
            $left = $b->script(<<<'JS'
                const form = document.createElement('form');
                form.setAttribute('onsubmit', document.querySelector('form[onsubmit*="ptah:draft:"]').getAttribute('onsubmit'));
                form.onsubmit();
                return [localStorage.getItem('ptah:draft:x:y:new'), localStorage.getItem('outra-chave')];
            JS)[0];

            $this->assertNull($left[0], 'O rascunho sobreviveu ao logout.');
            $this->assertSame('1', $left[1], 'O logout apagou chave que nao e rascunho.');
        });
    }
}
