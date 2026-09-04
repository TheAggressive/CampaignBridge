import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch } from '@wordpress/data';

interface BlockEditorPersistenceActions {
  __unstableMarkLastChangeAsPersistent: () => void;
}

/** Isolate the unstable core action needed to match core/editor save behavior. */
export function usePersistentChangeBoundary(): () => void {
  const actions = useDispatch(
    blockEditorStore
  ) as unknown as BlockEditorPersistenceActions;

  return actions.__unstableMarkLastChangeAsPersistent;
}
