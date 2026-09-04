import { Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { closeSmall } from '@wordpress/icons';
import ListView from '../../wordpress/ListView';

interface BlockEditorSelectors {
  getSelectedBlockClientId: () => string | null;
  getBlocks: () => unknown[];
}

interface SecondarySidebarProps {
  onClose: () => void;
}

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
 * <SecondarySidebar onClose={closeListView} />
 * ```
 */
export default function SecondarySidebar({
  onClose,
}: SecondarySidebarProps): JSX.Element {
  // Get the selected block and root blocks count for display
  const { selectedBlockClientId, blockCount } = useSelect(select => {
    const blockEditorSelect = select(
      'core/block-editor'
    ) as unknown as BlockEditorSelectors;
    return {
      selectedBlockClientId: blockEditorSelect.getSelectedBlockClientId(),
      blockCount: blockEditorSelect.getBlocks().length,
    };
  }, []);

  return (
    <div className='interface-complementary-area cb-editor__sidebar cb-editor__sidebar--secondary'>
      <div
        className='components-panel__header interface-complementary-area-header'
        tabIndex={-1}
      >
        <h2 className='interface-complementary-area-header__title'>
          {__('List view', 'campaignbridge')}
        </h2>
        <Button
          icon={closeSmall}
          label={__('Close list view', 'campaignbridge')}
          onClick={onClose}
          showTooltip
          size='compact'
        />
      </div>
      <div className='cb-editor__sidebar-content'>
        <div className='cb-editor__sidebar-content-inner'>
          {blockCount > 0 ? (
            <ListView
              rootClientId=''
              selectedBlockClientId={selectedBlockClientId}
              showNestedBlocks
              showBlockMovers={false}
              showAppender={false}
            />
          ) : (
            <div className='cb-editor__list-view-empty'>
              {__(
                'No blocks found. Add some content to see the block structure.',
                'campaignbridge'
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
