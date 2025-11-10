// @ts-ignore
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __experimentalListView as ListView } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

/**
 * Secondary Sidebar Component
 *
 * Displays a hierarchical list view of all blocks in the current post.
 * Shows the block structure and allows navigation through the block tree.
 * This component is displayed in the secondary sidebar area.
 *
 * Features:
 * - Hierarchical block list with nesting visualization
 * - Shows selected block highlighting
 * - Empty state when no blocks exist
 * - Auto-scrolling to selected blocks
 * - Keyboard navigation support
 *
 * @example
 * ```jsx
 * <SecondarySidebar />
 * ```
 */
export default function SecondarySidebar(): JSX.Element {
  // Get the selected block and root blocks count for display
  const { selectedBlockClientId, blockCount } = useSelect((select: any) => {
    const blockEditorSelect = select('core/block-editor');
    return {
      selectedBlockClientId: blockEditorSelect.getSelectedBlockClientId(),
      blockCount: blockEditorSelect.getBlocks().length,
    };
  }, []);

  return (
    <div
      className='cb-editor__sidebar-content-inner'
      style={{ height: '100%', overflowX: 'hidden' }}
    >
      {blockCount > 0 ? (
        <ListView
          rootClientId=''
          selectedBlockClientId={selectedBlockClientId}
          showNestedBlocks
          showBlockMovers={false}
          showAppender={false}
        />
      ) : (
        <div style={{ padding: '16px', color: '#666' }}>
          No blocks found. Add some content to see the block structure.
        </div>
      )}
    </div>
  );
}
