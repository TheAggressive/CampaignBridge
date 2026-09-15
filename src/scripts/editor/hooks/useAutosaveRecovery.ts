import { store as coreStore } from '@wordpress/core-data';
import { dispatch, resolveSelect, select } from '@wordpress/data';
import { useCallback, useEffect, useState } from '@wordpress/element';
import {
  getRecoveryEdits,
  isRecoverableAutosave,
} from '../utils/autosaveRecovery';

type RecoveryState = 'checking' | 'available' | 'dismissed' | 'error';

/** The recovery data stays in core-data; this hook owns only the prompt. */
export function useAutosaveRecovery(
  postType: string,
  postId: number,
  ready: boolean,
  canRestore: () => boolean
) {
  const [state, setState] = useState<RecoveryState>('checking');

  useEffect(() => {
    if (!ready) {
      return;
    }
    let cancelled = false;
    setState('checking');
    void (async () => {
      try {
        const resolver = resolveSelect(coreStore) as any;
        const user = await resolver.getCurrentUser();
        if (!user?.id) {
          throw new Error('Current user unavailable.');
        }
        await resolver.getAutosaves(postType, postId);
        const core = select(coreStore) as any;
        if (
          core.getResolutionError('getAutosaves', [postType, postId]) ||
          !core.hasFetchedAutosaves(postType, postId)
        ) {
          throw new Error('Autosaves unavailable.');
        }
        const canonical = core.getRawEntityRecord('postType', postType, postId);
        const autosave = core.getAutosave(postType, postId, user.id);
        const available = isRecoverableAutosave(canonical, autosave, user.id);
        if (!cancelled) {
          setState(available ? 'available' : 'dismissed');
        }
      } catch {
        if (!cancelled) {
          setState('error');
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [postId, postType, ready]);

  const restore = useCallback(() => {
    if (state !== 'available' || !canRestore()) {
      return;
    }
    try {
      const core = select(coreStore) as any;
      const user = core.getCurrentUser();
      const canonical = core.getRawEntityRecord('postType', postType, postId);
      const autosave = core.getAutosave(postType, postId, user.id);
      if (!isRecoverableAutosave(canonical, autosave, user.id)) {
        setState('dismissed');
        return;
      }
      const edits = getRecoveryEdits(autosave);
      // The clean editor may still hold transient parsed blocks. Clear those
      // before applying content so useEntityBlockEditor parses the recovery.
      const actions = dispatch(coreStore) as any;
      actions.clearEntityRecordEdits('postType', postType, postId);
      actions.editEntityRecord('postType', postType, postId, edits);
      setState('dismissed');
    } catch {
      setState('error');
    }
  }, [canRestore, postId, postType, state]);

  // Like dismissing WordPress's recovery notice, this is session UI state.
  // The autosave row remains owned by WordPress and is never deleted here.
  const discard = useCallback(() => setState('dismissed'), []);

  return { state, restore, discard, blocksPersistence: state !== 'dismissed' };
}
