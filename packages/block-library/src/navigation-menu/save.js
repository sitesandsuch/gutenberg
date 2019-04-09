/**
 * WordPress dependencies
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return (
		<ul>
			<InnerBlocks.Content />
		</ul>
	);
}
