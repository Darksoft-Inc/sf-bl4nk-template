#!/bin/bash
#
# Aliases for typical development activities
# Commands optimized for readability: use shell autocompletion !

# Useful utils
alias confirm='read -r -p "Are you sure? [y/N] " response && [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]'
alias set_exec='chmod u+x'

# Containers management
alias start_docker="docker compose up -d"
alias stop_docker="docker compose down"
alias shell_docker_pg="docker compose exec database bash"

# Server management
alias start_server="symfony server:start"
alias stop_server="symfony server:stop"

# Packages management
alias install_php_pkg="composer install"
alias update_php_pkg="composer update"
alias check_php_pkg="composer audit"
alias install_im_pkg="symfony console importmap:install"
alias update_im_pkg="symfony console importmap:update"
alias check_im_pkg="symfony console importmap:audit"

# Maintenance
alias install_asset_bundles="symfony console asset:install public"
alias build_scss="symfony console sass:build -v"
alias clear_cache="symfony console cache:clear --no-warmup"
alias clear_logs_dev="rm -f var/log/dev.log"
alias create_database="symfony console doctrine:database:create"
alias drop_database="confirm && symfony console doctrine:database:drop --force"
alias migrate_database="symfony console doctrine:migrations:migrate"
alias create_database_test="php bin/console doctrine:schema:create --env=test"
alias drop_database_test="php bin/console doctrine:schema:drop --force --env=test"

# Quality
alias lint_config="symfony console lint:yaml config/"
alias lint_autowiring="symfony console lint:container"
alias lint_translations="symfony console lint:translations"
alias lint_templates="symfony console lint:twig templates/"
alias lint_php="vendor/bin/phpstan --memory-limit=256M analyze"
alias lint_doctrine="symfony console doctrine:schema:validate"
alias lint_php_cs="vendor/bin/php-cs-fixer fix --dry-run"
alias format_php="vendor/bin/php-cs-fixer fix"
alias run_tests="vendor/bin/pest -v"

# Debug
alias debug_routes="symfony console debug:router"
alias debug_services="symfony console debug:container && symfony console debug:autowiring"
alias debug_templates="symfony console debug:twig"
alias debug_assets="symfony console debug:asset-map"
alias debug_scheduler="symfony console debug:scheduler"

# Boilerplate
alias make_entity="symfony console make:entity"
alias make_migration="symfony console make:migration"
alias make_command="symfony console make:command"
alias make_controller="symfony console make:controller"
alias make_form="symfony console make:form"

# Pipelines
update_all_packages()
{
    update_php_pkg &&
    update_im_pkg
}
setup_application()
{
    install_php_pkg &&
    install_im_pkg &&
    install_asset_bundles &&
    build_scss
}
run_all_checks()
{
    lint_config &&
    lint_autowiring &&
    lint_translations &&
    lint_templates &&
    lint_php &&
    lint_doctrine &&
    run_tests
}
run_essential_checks()
{
    lint_php &&
    lint_doctrine &&
    run_tests
}
boot_dev_server()
{
    start_docker &&
    start_server
}
