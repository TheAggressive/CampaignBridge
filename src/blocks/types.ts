export interface EmailBlockContext {
  'campaignbridge:postId'?: number;
  'campaignbridge:postType'?: string;
}

export interface EmailBlockEditProps<Attributes> {
  attributes: Attributes;
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
  style?: 'button' | 'link';
  linkColor?: string;
}

export interface PostLinkAttributes {
  label?: string;
  destination?: string;
  customUrl?: string;
  linkColor?: string;
  align?: 'left' | 'center' | 'right';
}
