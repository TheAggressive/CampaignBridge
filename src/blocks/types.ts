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

export interface PostButtonAttributes {
  label?: string;
  destination?: string;
  customUrl?: string;
  backgroundColor?: string;
  textColor?: string;
  align?: 'left' | 'center' | 'right';
  style?: NativeStyle;
  className?: string;
  linkColor?: string;
}

export interface PostLinkAttributes {
  style?: NativeStyle;
  label?: string;
  destination?: string;
  customUrl?: string;
  linkColor?: string;
  align?: 'left' | 'center' | 'right';
}
