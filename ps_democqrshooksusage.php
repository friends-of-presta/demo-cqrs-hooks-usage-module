<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;

//todo: demonstrate how include custom js extensions for existing grids maybe?.
/**
 * Class Ps_DemoCQRSHooksUsage demonstrates the usage of CQRS and hooks.
 */
class Ps_DemoCQRSHooksUsage extends Module
{
    public function __construct()
    {
        $this->name = 'ps_democqrshooksusage';
        $this->version = '1.0.0';
        $this->author = 'Tomas Ilginis';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->getTranslator()->trans(
            'Demo for CQRS and hooks usage',
            [],
            'Modules.Ps_DemoCQRSHooksUsage'
        );

        $this->description =
            $this->getTranslator()->trans(
                'Help developers to understand how to create module using new hooks and apply best practices when using CQRS',
                [],
                'Modules.Ps_DemoCQRSHooksUsage'
            );

        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_,
        ];
    }

    /**
     * Install module and register hooks to allow grid modification.
     *
     * @return bool
     */
    public function install()
    {
        return parent::install() &&
            // Register hook to allow Customer grid definition modifications.
            // Each grid's definition modification hook has it's own name. Hook name is built using
            // this structure: "action{grid_id}GridDefinitionModifier", in this case "grid_id" is "customer"
            // this means we will be modifying "Sell > Customers" page grid.
            // You can check any definition factory service in PrestaShop\PrestaShop\Core\Grid\Definition\Factory
            // to see available grid ids. Grid id is returned by `getId()` method.
            $this->registerHook('actionCustomerGridDefinitionModifier') &&
            // Register hook to allow Customer grid query modifications which allows to add any sql condition.
            $this->registerHook('actionCustomerGridQueryBuilderModifier') &&
            $this->installTables()
        ;
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallTables();
    }

    /**
     * Hooks allows to modify Customers grid definition.
     * This hook is a right place to add/remove columns or actions (bulk, grid).
     *
     * @param array $params
     */
    public function hookActionCustomerGridDefinitionModifier(array $params)
    {
        /** @var GridDefinitionInterface $definition */
        $definition = $params['definition'];

        $translator = $this->getTranslator();

        $definition
            ->getColumns()
            ->addAfter(
                'optin',
                (new ToggleColumn('is_allowed_for_review'))
                    ->setName($translator->trans('Allowed for review', [], 'Modules.Ps_DemoCQRSHooksUsage'))
                    ->setOptions([
                        'field' => 'is_allowed_for_review',
                        'primary_field' => 'id_customer',
                        'route' => 'ps_democqrshooksusage_toggle_is_allowed_for_review',
                        'route_param_name' => 'customerId',
                    ])
            )
        ;
    }

    /**
     * Hooks allows to modify Customers query builder and add custom sql statements.
     *
     * @param array $params
     */
    public function hookActionCustomerGridQueryBuilderModifier(array $params)
    {
        /** @var QueryBuilder $searchQueryBuilder */
        $searchQueryBuilder = $params['search_query_builder'];

        $searchQueryBuilder->addSelect(
            'IF(dcur.`is_allowed_for_review` IS NULL,0,dcur.`is_allowed_for_review`) AS `is_allowed_for_review`'
        );

        $searchQueryBuilder->leftJoin(
            'c',
            '`' . pSQL(_DB_PREFIX_) . 'democqrshooksusage_reviewer`',
            'dcur',
            'dcur.`id_customer` = c.`id_customer`'
        );
    }

    /**
     * Installs sample tables required for demonstration.
     *
     * @return bool
     */
    private function installTables()
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS `' . pSQL(_DB_PREFIX_) . 'democqrshooksusage_reviewer` (
                `id_reviewer` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_customer` INT(10) UNSIGNED NOT NULL,
                `is_allowed_for_review` TINYINT(1) NOT NULL,
                PRIMARY KEY (`id_reviewer`)
            ) ENGINE=' . pSQL(_MYSQL_ENGINE_) . ' COLLATE=utf8_unicode_ci;
        ';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Uninstalls sample tables required for demonstration.
     *
     * @return bool
     */
    private function uninstallTables()
    {
        $sql = 'DROP TABLE IF EXISTS `' . pSQL(_DB_PREFIX_) . 'democqrshooksusage_reviewer`';

        return Db::getInstance()->execute($sql);
    }
}
