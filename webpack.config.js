const path = require('path');

module.exports = {
  entry: {
    admin_customer: './assets/js/admin/grid/customer/index.js',
  },
  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, './views/dist'),
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: ['babel-loader'],
      },
    ],
  },
  stats: {
    colors: true,
  },
  devtool: 'source-map',
};
