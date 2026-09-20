import type { NativeStyle } from './shared/native-styles';
export interface EmailBlockContext {
  'campaignbridge:postId'?: number;
  'campaignbridge:postType'?: string;
}

export interface EmailBlockEditProps<Attributes> {
  attributes: Attributes & { style?: NativeStyle; className?: string };
  setAttributes: (attributes: Partial<Attributes>) => void;
  clientId: string;
  context?: EmailBlockContext;
}
