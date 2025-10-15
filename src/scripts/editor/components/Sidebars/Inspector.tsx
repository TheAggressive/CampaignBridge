import { BlockInspector } from '@wordpress/block-editor';
import { Panel } from '@wordpress/components';

/**
 * Block Inspector Panel Component
 *
 * Wraps the WordPress BlockInspector component in a collapsible panel
 * for the sidebar. Provides access to block settings, attributes, and
 * advanced options for the currently selected block in the editor.
 *
 * @returns {JSX.Element} The block inspector panel containing block controls
 *
 * @example
 * ```jsx
 * <Inspector />
 * ```
 */
export default function Inspector() {
  return (
    <Panel>
      <BlockInspector />
    </Panel>
  );
}
