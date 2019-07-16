<?php

namespace DemoCQRSHooksUsage\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;

class CustomerPositionController extends FrameworkBundleAdminController
{
    public function updatePositionAction($customerId, Request $request)
    {
        // update your column positions here.

        return $this->redirectToRoute('admin_customers_index');
    }
}
