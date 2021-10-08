const path = require('path');
const webpack = require('webpack');
const entries = require('../setup/entries.js');
const webpackCustom = require('../setup/webpack.js');
const CompressionPlugin = require('compression-webpack-plugin');

const webpackDir = path.resolve(__dirname);
const javascriptDir = path.resolve(webpackDir, '..');
const srcDir = path.resolve(javascriptDir, 'src');
const addonDir = path.resolve(javascriptDir, '..');
const outputDir = addonDir;

const entryObj = {};
for (let i = 0, len = entries.length; i < len; i++) {
    const entry = entries[i];
    entryObj[entry[1]] = path.join(srcDir, entry[0]); // output full
}

module.exports = Object.assign({}, {
    entry: entryObj,
    output: {
        path: outputDir,
        filename: '[name].js',
    },
    module: {
        loaders: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                loader: 'babel-loader',
                include: srcDir,
                query: {
                    cacheDirectory: path.resolve(javascriptDir, 'tmpCache'),
                },
            },
        ],
    },
    externals: {
        jQuery: '$',
    },
    plugins: [],
}, webpackCustom);
