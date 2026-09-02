import { Button } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { __, isRTL } from '@wordpress/i18n';
import { displayShortcut, isAppleOS } from '@wordpress/keycodes';
import { redo as redoIcon, undo as undoIcon } from '@wordpress/icons';

/**
 * WordPress core-data history controls for the active template entity.
 */
export default function HistoryControls(): JSX.Element {
  const { hasUndo, hasRedo } = useSelect(
    select => ({
      hasUndo: select(coreStore).hasUndo(),
      hasRedo: select(coreStore).hasRedo(),
    }),
    []
  );
  const { undo, redo } = useDispatch(coreStore);

  return (
    <div role='toolbar' aria-label={__('Editor history', 'campaignbridge')}>
      <Button
        __next40pxDefaultSize
        icon={!isRTL() ? undoIcon : redoIcon}
        label={__('Undo', 'campaignbridge')}
        shortcut={displayShortcut.primary('z')}
        aria-disabled={!hasUndo}
        onClick={hasUndo ? undo : undefined}
      />
      <Button
        __next40pxDefaultSize
        icon={!isRTL() ? redoIcon : undoIcon}
        label={__('Redo', 'campaignbridge')}
        shortcut={
          isAppleOS()
            ? displayShortcut.primaryShift('z')
            : displayShortcut.primary('y')
        }
        aria-disabled={!hasRedo}
        onClick={hasRedo ? redo : undefined}
      />
    </div>
  );
}
