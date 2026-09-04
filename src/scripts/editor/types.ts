/** Shared contracts for the standalone email editor. */

export interface TemplateSummary {
  id: number;
  title: string;
  status: string;
  date?: string;
}

export interface TemplateRestRecord {
  id: number;
  title: string | { raw?: string; rendered?: string };
  status: string;
  date?: string;
}

export type SidebarTab = 'template-settings' | 'block-inspector';

export type SaveStatus = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';

export interface EditorStyle {
  css: string;
  baseURL?: string;
  ignoredSelectors?: Array<string | RegExp>;
}

export interface EmailEditorSettings extends Record<string, unknown> {
  allowedBlockTypes?: string[];
  styles?: EditorStyle[];
}
