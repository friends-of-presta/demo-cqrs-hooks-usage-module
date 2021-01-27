import Grid from '../../../../../../../admin-dev/themes/new-theme/js/components/grid/grid';
import PositionExtension
  from '../../../../../../../admin-dev/themes/new-theme/js/components/grid/extension/position-extension';

const $ = window.$;

$(() => {
  const customerGrid = new Grid('customer');
  customerGrid.addExtension(new PositionExtension());
});
