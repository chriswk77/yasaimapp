/**
 * The chat bubble in the header of the pages with the app's buttons
 * (header_chat in includes/page.php): open and close its list.
 *
 * ON THE DESKTOP a row opens the conversation RIGHT HERE, floating over
 * the page (chat.js, 14.10.2026), and a chat left open on the page before
 * comes back by itself. On the phone the rows stay links to the map,
 * which opens the conversation in its full-screen window.
 *
 * On every page, because page_footer() loads it everywhere; where the
 * header carries no bubble it does nothing.
 */

const bubble = document.getElementById('header-chat');
const list = document.getElementById('header-chat-list');

if (bubble && list) {
    const close = () => {
        list.hidden = true;
        bubble.setAttribute('aria-expanded', 'false');
    };
    bubble.addEventListener('click', (e) => {
        e.stopPropagation();
        list.hidden = !list.hidden;
        bubble.setAttribute('aria-expanded', String(!list.hidden));
    });
    // A click anywhere else or Escape closes it — a menu, not a window.
    document.addEventListener('click', (e) => {
        if (!list.hidden && !list.contains(e.target)) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !list.hidden) close();
    });
}

/* THE FLOATING CHAT (chat.js). Loaded only where the page carries the
   window (chat_window(), printed with the bubble) and only when wanted:
   a row clicked on the desktop, or a chat the page before left open. */
const chatWindow = document.getElementById('chat-window');
const desk = matchMedia('(min-width: 861px)');
let kept = null;
try { kept = sessionStorage.getItem('chat.floating'); } catch { /* private window */ }
if (chatWindow && desk.matches && kept) import('./chat.js');

if (chatWindow && bubble && list) {
    list.addEventListener('click', async (e) => {
        const row = e.target.closest('a[data-friend]');
        if (!row || !desk.matches) return;          // the phone follows the link
        e.preventDefault();
        list.hidden = true;
        bubble.setAttribute('aria-expanded', 'false');
        const chat = await import('./chat.js');
        chat.openFriendChat({ id: Number(row.dataset.friend), name: row.dataset.name }, Number(bubble.dataset.me));
        // Read now — the dots of this page's loading go with it.
        row.classList.remove('has-unread');
        if (!list.querySelector('.has-unread')) bubble.classList.remove('has-unread');
    });
}

