import { EMAIL_BLOCK_NESTING } from '../shared/nesting';

/**
 * Child blocks supported by the column email grammar.
 *
 * Single source of truth: `../shared/nesting`, which mirrors
 * `Column_Renderer::allowed_children()` so the editor and compiler accept the
 * same block trees.
 */
export const COLUMN_ALLOWED_BLOCKS = [...EMAIL_BLOCK_NESTING.column];
