import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

interface BlockEditorDispatch {
  __unstableMarkLastChangeAsPersistent: () => void;
}

interface EditorEffectsProps {
  saveStatus: string;
  onBlockSelected: () => void;
}

/**
 * Coordinate shell behavior that must run inside BlockEditorProvider's scoped
 * data registry.
 */
export default function EditorEffects({
  saveStatus,
  onBlockSelected,
}: EditorEffectsProps): null {
  const selectedClientId = useSelect(
    select => select(blockEditorStore).getSelectedBlockClientId(),
    []
  );
  const { __unstableMarkLastChangeAsPersistent } = useDispatch(
    blockEditorStore
  ) as unknown as BlockEditorDispatch;
  const wasSavingRef = useRef(false);

  useEffect(() => {
    if (selectedClientId) {
      onBlockSelected();
    }
  }, [onBlockSelected, selectedClientId]);

  useEffect(() => {
    const isSaving = saveStatus === 'saving';

    if (wasSavingRef.current && saveStatus === 'saved') {
      // Match core/editor's successful regular-save lifecycle. Without this
      // boundary, another edit to the same attribute remains transient and
      // never marks the entity dirty again.
      __unstableMarkLastChangeAsPersistent();
    }

    wasSavingRef.current = isSaving;
  }, [__unstableMarkLastChangeAsPersistent, saveStatus]);

  return null;
}
