<?php
/**
 * The chat window — one <dialog> for the friend chats and the chats of
 * shared lists. On the map since 20.08.2026; SINCE 14.10.2026 ALSO ON EVERY
 * PAGE WITH THE APP'S BUTTONS (Fundbrowser, Bestimmung, Verwaltung), so a
 * conversation stays open while one changes between them („ein
 * Chat muss diese ganzen Module oder Bereiche überstehen"). The styles are
 * assets/css/chat.css, the behaviour assets/js/chat.js.
 */

declare(strict_types=1);

function chat_window(): void
{
    ?>
    <?php /* The container chat ---------------------------------------------------

         ONE DIALOG FOR BOTH SHAPES. On the phone it covers the screen, on
         the desktop it stands as a window in the middle — that is a
         question of the stylesheet, not of the markup, and two dialogs
         would be two places to maintain the same conversation.

         The conversation scrolls, the writing line does not: the line
         stays at the bottom edge where the thumb is, and only the messages
         move underneath it. Hence the flex column with a fixed footer, and
         hence NO second scroll area inside it (trap 4 in CLAUDE.md). */ ?>
    <dialog class="chat-window" id="chat-window">
        <div class="chat-head">
            <h2 id="chat-heading"><?= h(t('Chat')) ?></h2>
            <span class="chat-head-buttons">
                <?php /* Fold to the head — only where the window floats (desktop),
                     see the end of chat.css. */ ?>
                <button type="button" class="symbol-button" id="chat-minimize"
                        title="<?= h(t('Zuklappen')) ?>" aria-label="<?= h(t('Chat zuklappen')) ?>" aria-expanded="true"><?= icon('minus') ?></button>
                <button type="button" class="symbol-button" id="chat-close"
                        title="<?= h(t('Schließen')) ?>" aria-label="<?= h(t('Chat schließen')) ?>"><?= icon('close') ?></button>
            </span>
        </div>

        <?php /* ONE ELEMENT, TWO SHAPES — the same trick as the window itself.
             On the desktop the members stand as a column beside the
             conversation, on the phone as a fold-out above it. That is a
             <details>: on the phone it behaves like one, on the desktop
             the stylesheet hides its summary and shows the list for good.
             No script decides it, so nothing can decide it differently in
             the two places. */ ?>
        <div class="chat-body">
            <details class="chat-people" id="chat-people">
                <?php /* The triangle from the library, as with every summary
                     in this project: the browser's own marker is hidden
                     (app.css), so without it nothing would say that this
                     folds. It turns by itself when open. */ ?>
                <summary><?= icon('fold-triangle', ['class' => 'collapse-triangle']) ?><?= h(t('Wer liest mit')) ?></summary>
                <ul class="chat-people-list" id="chat-members"></ul>
            </details>

            <div class="chat-main">
                <div class="chat-messages" id="chat-messages" aria-live="polite"></div>

                <form class="chat-write" id="chat-form">
                    <?php /* No label: the field stands alone at the bottom edge
                         and says with its placeholder what it is. A label
                         above it would cost a line the conversation needs. */ ?>
                    <textarea id="chat-text" rows="1" maxlength="10000"
                              placeholder="<?= h(t('Nachricht schreiben …')) ?>" aria-label="<?= h(t('Nachricht')) ?>"></textarea>
                    <button type="submit" class="button" id="chat-send"><?= h(t('Senden')) ?></button>
                </form>
            </div>
        </div>
    </dialog>
    <?php
}
