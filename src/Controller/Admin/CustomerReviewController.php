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

namespace DemoCQRSHooksUsage\Controller\Admin;

use DemoCQRSHooksUsage\Domain\Reviewer\Command\ToggleIsAllowedToReviewCommand;
use DemoCQRSHooksUsage\Domain\Reviewer\Exception\CannotCreateReviewerException;
use DemoCQRSHooksUsage\Domain\Reviewer\Exception\CannotToggleAllowedToReviewStatusException;
use DemoCQRSHooksUsage\Domain\Reviewer\Exception\ReviewerException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerException;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * This controller holds all custom actions which are added by extending "Sell > Customers" page.
 * @see https://devdocs.prestashop.com/1.7/modules/concepts/controllers/admin-controllers/ for more details.
 */
class CustomerReviewController extends FrameworkBundleAdminController
{
    /**
     * Catches the toggle action of customer review.
     *
     * @param int $customerId
     *
     * @return RedirectResponse
     */
    public function toggleIsAllowedForReviewAction($customerId)
    {
        try {
            /**
             * This part demonstrates the usage of CQRS pattern command to perform write operation for Reviewer entity.
             * @see https://devdocs.prestashop.com/1.7/development/architecture/cqrs/ for more detailed information.
             *
             * As this is our recommended approach of writing the data but we not force to use this pattern in modules -
             * you can use directly an entity here or wrap it in custom service class.
             */
            $this->getCommandBus()->handle(new ToggleIsAllowedToReviewCommand((int)$customerId));

            $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));
        } catch (DomainException $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessageMapping()));
        }

        return $this->redirectToRoute('admin_customers_index');
    }

    /**
     * Gets error message mappings which are later used to display friendly user error message instead of the
     * exception message.
     *
     * @see https://devdocs.prestashop.com/1.7/development/architecture/domain-exceptions/ for more detailed explanation
     *
     * @return array
     */
    private function getErrorMessageMapping()
    {
        return [
            CustomerException::class => $this->trans(
                'Something bad happened when trying to get customer id',
                'Modules.Ps_DemoCQRSHooksUsage'
            ),
            CannotCreateReviewerException::class => $this->trans(
                'Failed to create reviewer',
                'Modules.Ps_DemoCQRSHooksUsage'
            ),
            CannotToggleAllowedToReviewStatusException::class => $this->trans(
                'An error occurred while updating the status.',
                'Admin.Notifications.Error'
            )
        ];
    }
}
