import { store as coreDataStore, useEntityRecords } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
import type { TemplateRestRecord, TemplateSummary } from '../types';

const TEMPLATE_POST_TYPE = 'cb_templates';

/**
 * Keep this query aligned with Editor_Data_Preload so core-data can consume
 * the server-preloaded response without issuing another request.
 */
export const TEMPLATE_LIST_QUERY = Object.freeze({
  per_page: 100,
  status: 'draft,publish',
  context: 'edit',
  _fields: 'id,title,status,date',
});

interface UseTemplatesOptions {
  onError?: (message: string) => void;
}

function templateTitle(record: TemplateRestRecord): string {
  if (typeof record.title === 'string') {
    return record.title || `#${record.id}`;
  }

  return record.title.raw || record.title.rendered || `#${record.id}`;
}

function errorMessage(error: unknown): string {
  return error instanceof Error && error.message
    ? error.message
    : 'Failed to load templates.';
}

/** Read template summaries through WordPress core-data's resolver and cache. */
export function useTemplates({ onError }: UseTemplatesOptions = {}) {
  const { records, isResolving, hasResolved } =
    useEntityRecords<TemplateRestRecord>(
      'postType',
      TEMPLATE_POST_TYPE,
      TEMPLATE_LIST_QUERY
    );

  const resolutionError = useSelect(
    select =>
      select(coreDataStore).getResolutionError('getEntityRecords', [
        'postType',
        TEMPLATE_POST_TYPE,
        TEMPLATE_LIST_QUERY,
      ]),
    []
  );

  const error = resolutionError ? errorMessage(resolutionError) : '';

  useEffect(() => {
    if (error) {
      onError?.(error);
    }
  }, [error, onError]);

  const items = useMemo<TemplateSummary[]>(
    () =>
      (records || []).map(record => ({
        id: record.id,
        title: templateTitle(record),
        status: record.status,
        date: record.date,
      })),
    [records]
  );

  return {
    items,
    loading: isResolving || !hasResolved,
    error,
  };
}
