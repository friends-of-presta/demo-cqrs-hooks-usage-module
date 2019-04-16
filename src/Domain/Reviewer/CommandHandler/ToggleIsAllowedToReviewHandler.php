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
use Doctrine\DBAL\Connection;
use PDO;
use PrestaShopException;

/**
 * Used for toggling the customer if is allowed to make a review.
 */
class ToggleIsAllowedToReviewHandler
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $dbPrefix;

    /**
     * @param Connection $connection
     * @param string $dbPrefix
     */
    public function __construct(Connection $connection, $dbPrefix)
    {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }

    /**
     * @param ToggleIsAllowedToReviewCommand $command
     *
     * @throws CannotCreateReviewerException
     * @throws CannotToggleAllowedToReviewStatusException
     */
    public function handle(ToggleIsAllowedToReviewCommand $command)
    {
        $reviewerId = $this->getReviewerId($command);

        $reviewer = new Reviewer($reviewerId);

        if (0 >= $reviewer->id) {
            $reviewer = $this->createReviewer($command);
        }

        $reviewer->is_allowed_for_review = (bool) !$reviewer->is_allowed_for_review;

        try {
            if (false === $reviewer->update()) {
                throw new CannotToggleAllowedToReviewStatusException(
                    sprintf('Failed to change status for reviewer with id "%s"', $reviewer->id)
                );
            }
        } catch (PrestaShopException $exception) {
            throw new CannotToggleAllowedToReviewStatusException(
                'An unexpected error occurred when updating reviewer status'
            );
        }
    }

    /**
     * Gets reviewer id.
     *
     * @param ToggleIsAllowedToReviewCommand $command
     *
     * @return int
     */
    private function getReviewerId(ToggleIsAllowedToReviewCommand $command)
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->select('`id_reviewer`')
            ->from($this->dbPrefix . 'democqrshooksusage_reviewer')
            ->where('`id_customer` = :customer_id')
        ;

        $queryBuilder->setParameter('customer_id', $command->getCustomerId()->getValue());

        return (int) $queryBuilder->execute()->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * Creates a reviewer.
     *
     * @param ToggleIsAllowedToReviewCommand $command
     *
     * @return Reviewer
     *
     * @throws CannotCreateReviewerException
     */
    private function createReviewer(ToggleIsAllowedToReviewCommand $command)
    {
        try {
            $reviewer = new Reviewer();
            $reviewer->id_customer = $command->getCustomerId()->getValue();
            $reviewer->is_allowed_for_review = 0;

            if (false === $reviewer->save()) {
                throw new CannotCreateReviewerException(
                    sprintf(
                        'An error occurred when creating reviewer with customer id "%s"',
                        $command->getCustomerId()->getValue()
                    )
                );
            }
        } catch (PrestaShopException $exception) {
            throw new CannotCreateReviewerException(
                sprintf(
                    'An unexpected error occurred when creating reviewer with customer id "%s"',
                    $command->getCustomerId()->getValue()
                ),
                0,
                $exception
            );
        }

        return $reviewer;
    }
}
