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
