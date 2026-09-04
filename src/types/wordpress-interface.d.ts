declare module '@wordpress/interface' {
  import type { ComponentType, ReactNode } from 'react';

  interface ComplementaryAreaProps {
    scope: string;
    identifier: string;
    className?: string;
    isPinnable?: boolean;
    header?: ReactNode;
    children?: ReactNode;
  }

  interface ComplementaryAreaSlotProps {
    scope: string;
    identifier: string;
  }

  export const ComplementaryArea: ComponentType<ComplementaryAreaProps> & {
    Slot: ComponentType<ComplementaryAreaSlotProps>;
  };

  export const FullscreenMode: ComponentType<{ isActive: boolean }>;

  export const InterfaceSkeleton: ComponentType<{
    className?: string;
    header?: ReactNode;
    content?: ReactNode;
    sidebar?: ReactNode;
    secondarySidebar?: ReactNode;
    footer?: ReactNode;
    labels?: { secondarySidebar?: string };
  }>;
}
