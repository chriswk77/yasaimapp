# Modules

What is not part of the application lives here: one folder per module, each
a repository of its own that this one ignores. An installation without any
module is complete.

A module is a folder `modules/<name>/` with a file `module.php` that
RETURNS what it offers — the answers to the questions the application asks
at its hooks. `includes/modules.php` lists the hooks and says what else a
module may bring in its folder (pages, scripts, styles, migrations, a
language catalog, tests). The application never calls a module by name;
`tests/modules.php` is the tripwire.

To install one, clone it into this folder under its name and apply its
migrations in the administration.
