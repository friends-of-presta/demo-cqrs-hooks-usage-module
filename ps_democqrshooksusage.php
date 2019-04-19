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

use DemoCQRSHooksUsage\Domain\Reviewer\Command\UpdateIsAllowedToReviewCommand;
use DemoCQRSHooksUsage\Domain\Reviewer\Exception\CannotCreateReviewerException;
use DemoCQRSHooksUsage\Domain\Reviewer\Exception\CannotToggleAllowedToReviewStatusException;
use DemoCQRSHooksUsage\Domain\Reviewer\Exception\ReviewerException;
use DemoCQRSHooksUsage\Domain\Reviewer\Query\GetReviewerSettingsForForm;
use DemoCQRSHooksUsage\Domain\Reviewer\QueryResult\ReviewerSettingsForForm;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerException;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Search\Filters\CustomerFilters;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\YesAndNoChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

//todo: demonstrate how include custom js extensions for existing grids maybe?.
//todo: not a single translation works for this module
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
            // Register hook to allow overriding customer form
            // this structure: "action{block_prefix}FormBuilderModifier", in this case "block_prefix" is "customer"
            // {block_prefix} is either retrieved automatically by its type. E.g "ManufacturerType" will be "manufacturer"
            // or it can be modified in form type by overriding "getBlockPrefix" function
            $this->registerHook('actioncustomerFormBuilderModifier') &&
            $this->registerHook('actionAfterCreatecustomerFormHandler') &&
            $this->registerHook('actionAfterUpdatecustomerFormHandler') &&
            $this->installTables()
        ;
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallTables();
    }

    /**
     * Hook allows to modify Customers grid definition.
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

        $definition->getFilters()->add(
            (new Filter('is_allowed_for_review', YesAndNoChoiceType::class))
            ->setAssociatedColumn('is_allowed_for_review')
        );
    }

    /**
     * Hook allows to modify Customers query builder and add custom sql statements.
     *
     * @param array $params
     */
    public function hookActionCustomerGridQueryBuilderModifier(array $params)
    {
        /** @var QueryBuilder $searchQueryBuilder */
        $searchQueryBuilder = $params['search_query_builder'];

        /** @var CustomerFilters $searchCriteria */
        $searchCriteria = $params['search_criteria'];

        $searchQueryBuilder->addSelect(
            'IF(dcur.`is_allowed_for_review` IS NULL,0,dcur.`is_allowed_for_review`) AS `is_allowed_for_review`'
        );

        $searchQueryBuilder->leftJoin(
            'c',
            '`' . pSQL(_DB_PREFIX_) . 'democqrshooksusage_reviewer`',
            'dcur',
            'dcur.`id_customer` = c.`id_customer`'
        );

        if ('is_allowed_for_review' === $searchCriteria->getOrderBy()) {
            $searchQueryBuilder->orderBy('dcur.`is_allowed_for_review`', $searchCriteria->getOrderWay());
        }

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ('is_allowed_for_review' === $filterName) {
                $searchQueryBuilder->andWhere('dcur.`is_allowed_for_review` = :is_allowed_for_review');
                $searchQueryBuilder->setParameter('is_allowed_for_review', $filterValue);

                if (!$filterValue) {
                    $searchQueryBuilder->orWhere('dcur.`is_allowed_for_review` IS NULL');
                }
            }
        }
    }

    /**
     * Hook allows to modify Customers form and add aditional form fields as well as modify or add new data to the forms.
     *
     * @param array $params
     */
    public function hookactioncustomerFormBuilderModifier(array $params)
    {
        /** @var FormBuilderInterface $formBuilder */
        $formBuilder = $params['form_builder'];
        $formBuilder->add('is_allowed_for_review', SwitchType::class);

        /** @var CommandBusInterface $queryBus */
        $queryBus = $this->get('prestashop.core.query_bus');

        /** @var ReviewerSettingsForForm $reviewerSettings */
        $reviewerSettings = $queryBus->handle(new GetReviewerSettingsForForm($params['id']));

        $params['data']['is_allowed_for_review'] = $reviewerSettings->isAllowedForReview();

        $formBuilder->setData($params['data']);
    }

    /**
     * Hook allows to modify Customers form and add aditional form fields as well as modify or add new data to the forms.
     *
     * @param array $params
     *
     * @throws CustomerException
     */
    public function hookactionAfterUpdatecustomerFormHandler(array $params)
    {
        $this->updateCustomerReviewStatus($params);
    }

    /**
     * Hook allows to modify Customers form and add aditional form fields as well as modify or add new data to the forms.
     *
     * @param array $params
     *
     * @throws CustomerException
     */
    public function hookactionAfterCreatecustomerFormHandler(array $params)
    {
        $this->updateCustomerReviewStatus($params);
    }

    /**
     * @param array $params
     *
     * @throws CustomerException
     */
    private function updateCustomerReviewStatus(array $params)
    {
        //todo: a better would be to grab the data from array?

        $customerId = $params['id'];
        /** @var Request $request */
        $request = $params['request'];
        /** @var array $customerFormData */
        $customerFormData = $request->get('customer');
        $isAllowedForReview = (bool) $customerFormData['is_allowed_for_review'];

        /** @var CommandBusInterface $commandBus */
        $commandBus = $this->get('prestashop.core.command_bus');

        try {
            $commandBus->handle(new UpdateIsAllowedToReviewCommand(
                $customerId,
                $isAllowedForReview
            ));
        } catch (ReviewerException $exception) {
            $this->handleException($exception);
        }
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

    /**
     * Handles exceptions and displays message in more user friendly form.
     *
     * @param ReviewerException $exception
     */
    private function handleException(ReviewerException $exception)
    {
        $exceptionDictionary = [
            CannotCreateReviewerException::class => $this->getTranslator()->trans(
                'Failed to create a record for customer',
                [],
                'Modules.Ps_DemoCQRSHooksUsage'
            ),
            CannotToggleAllowedToReviewStatusException::class => $this->getTranslator()->trans(
                'Failed to toggle is allowed to review status',
                [],
                'Modules.Ps_DemoCQRSHooksUsage'
            ),
        ];

        $exceptionType = get_class($exception);

        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->get('session')->getFlashBag();


        if (isset($exceptionDictionary[$exceptionType])) {
            $flashBag->add('error', $exceptionDictionary[$exceptionType]);

            return;
        }

        $fallbackMessage = $this->getTranslator()->trans(
            'An unexpected error occurred. [%type% code %code%]',
            [
                '%type%' => $exceptionType,
                '%code%' => $exception->getCode(),
            ],
            'Admin.Notifications.Error'
        );

        $flashBag->add('error', $fallbackMessage);
    }
}
