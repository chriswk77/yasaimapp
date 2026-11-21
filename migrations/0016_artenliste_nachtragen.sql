-- Mark existing "Meine Funde" lists after the fact.
--
-- 0014 only created the species list where there was none yet — and thereby
-- overlooked exactly the normal case: the first version of auth.php named
-- the starter list of every new account "Meine Funde". Whoever created their
-- account before the renaming already had the list, so the NOT EXISTS kicked
-- in, and the existing one stayed unmarked. Result: no species suggestions,
-- without anything looking like an error.
--
-- Locally it went unnoticed, because no list of that name existed there and
-- 0014 cleanly created it anew. A difference between the data sets that the
-- migration did not consider.
--
-- This one catches up on it. It is deliberately drawn narrowly: only the
-- exact name, only undeleted lists, and only where the mark is missing.
-- Every other list stays untouched — whoever wants to make another one a
-- species list sets the checkmark in the list editor.

UPDATE map_lists
   SET ist_arten = 1, geaendert_am = NOW()
 WHERE name = 'Meine Funde'
   AND geloescht_am IS NULL
   AND ist_arten = 0;
