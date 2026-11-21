# Shared datasets

Every `*.yaml` file in this directory loads in **every** environment,
before the files of `datasets/<environment>/`. Both sets load together in
one run, so records here can be referenced from an environment directory
and the other way around.

The starter ships no shared dataset: derived applications put the
records both development and production need, such as a scenario map,
here.

See `docs/SETUP.md` for the load command and `docs/DECISIONS.md` for the
format and the slug-keyed rules.
