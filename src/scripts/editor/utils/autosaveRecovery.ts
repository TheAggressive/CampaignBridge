/** Only editable fields exposed by WordPress's autosave response are restored. */
export interface RecoveryRecord {
  id: number;
  parent?: number;
  author?: number;
  status?: string;
  modified_gmt?: string;
  title?: string | { raw?: string };
  content?: string | { raw?: string };
  excerpt?: string | { raw?: string };
  meta?: Record<string, unknown>;
}

function raw(value: RecoveryRecord['content']): string | undefined {
  return typeof value === 'string' ? value : value?.raw;
}

export function getRecoveryEdits(
  autosave: RecoveryRecord,
  currentMeta?: Record<string, unknown>,
  revisionedMetaKeys: readonly string[] = []
) {
  const title = raw(autosave.title);
  const content = raw(autosave.content);
  // A partial/rendered-only response must never erase the editor's content.
  if (title === undefined || content === undefined) {
    throw new Error('Incomplete autosave.');
  }
  const excerpt = raw(autosave.excerpt);
  const meta = autosave.meta
    ? { ...(currentMeta ?? {}), ...autosave.meta }
    : undefined;
  if (meta && currentMeta && autosave.meta) {
    for (const key of revisionedMetaKeys) {
      if (!Object.prototype.hasOwnProperty.call(autosave.meta, key)) {
        meta[key] = '';
      }
    }
  }
  return {
    title,
    content,
    ...(excerpt !== undefined ? { excerpt } : {}),
    ...(meta ? { meta } : {}),
  };
}

export function isRecoverableAutosave(
  canonical: RecoveryRecord,
  autosave: RecoveryRecord | undefined,
  userId: number
): boolean {
  if (
    !autosave ||
    autosave.parent !== canonical.id ||
    autosave.author !== userId ||
    autosave.id === canonical.id
  ) {
    return false;
  }
  const savedTime = Date.parse(`${canonical.modified_gmt}Z`);
  const autosaveTime = Date.parse(`${autosave.modified_gmt}Z`);
  // WordPress treats autosaves at or before the canonical save as obsolete.
  if (!Number.isFinite(savedTime) || !Number.isFinite(autosaveTime)) {
    throw new Error('Missing autosave timestamps.');
  }
  if (autosaveTime <= savedTime) {
    return false;
  }
  const edits = getRecoveryEdits(autosave);
  return (
    edits.title !== raw(canonical.title) ||
    edits.content !== raw(canonical.content) ||
    (edits.excerpt !== undefined && edits.excerpt !== raw(canonical.excerpt)) ||
    Object.entries(edits.meta ?? {}).some(
      ([key, value]) =>
        JSON.stringify(value) !== JSON.stringify(canonical.meta?.[key])
    )
  );
}
