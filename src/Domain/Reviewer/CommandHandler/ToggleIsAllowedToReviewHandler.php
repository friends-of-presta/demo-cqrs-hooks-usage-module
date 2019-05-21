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

namespace DemoCQRSHooksUsage\Domain\Reviewer\CommandHandler;

use DemoCQRSHooksUsage\Domain\Reviewer\Command\ToggleIsAllowedToReviewCommand;
use DemoCQRSHooksUsage\Domain\Reviewer\Exception\CannotCreateReviewerException;
use DemoCQRSHooksUsage\Domain\Reviewer\Exception\CannotToggleAllowedToReviewStatusException;
use DemoCQRSHooksUsage\Entity\Reviewer;
use DemoCQRSHooksUsage\Repository\ReviewerRepository;
use PrestaShopException;

/**
 * Used for toggling the customer if is allowed to make a review.
 */
class ToggleIsAllowedToReviewHandler extends AbstractReviewerHandler
{
    /**
     * @var ReviewerRepository
     */
    private $reviewerRepository;

    /**
     * @param ReviewerRepository $reviewerRepository
     */
    public function __construct(ReviewerRepository $reviewerRepository)
    {
        $this->reviewerRepository = $reviewerRepository;
    }

    /**
     * @param ToggleIsAllowedToReviewCommand $command
     *
     * @throws CannotCreateReviewerException
     * @throws CannotToggleAllowedToReviewStatusException
     */
    public function handle(ToggleIsAllowedToReviewCommand $command)
    {
        $reviewerId = $this->reviewerRepository->findIdByCustomer($command->getCustomerId()->getValue());

        $reviewer = new Reviewer($reviewerId);

        if (0 >= $reviewer->id) {
            $reviewer = $this->createReviewer($command->getCustomerId()->getValue());
        }

        $reviewer->is_allowed_for_review = (bool) !$reviewer->is_allowed_for_review;

        try {
            if (false === $reviewer->update()) {
                throw new CannotToggleAllowedToReviewStatusException(
                    sprintf('Failed to change status for reviewer with id "%s"', $reviewer->id)
                );
            }
        } catch (PrestaShopException $exception) {
            /*
             * @see https://devdocs.prestashop.com/1.7/development/architecture/domain-exceptions/
             */
            throw new CannotToggleAllowedToReviewStatusException(
                'An unexpected error occurred when updating reviewer status'
            );
        }
    }
}
