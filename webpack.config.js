/**
 * Extends the @wordpress/scripts config with three entries.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( process.cwd(), 'src/admin/index.js' ),
		builder: path.resolve( process.cwd(), 'src/builder/index.js' ),
		blocks: path.resolve( process.cwd(), 'src/blocks/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'assets/build' ),
	},
};
