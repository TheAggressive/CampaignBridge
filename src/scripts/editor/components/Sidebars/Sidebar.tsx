import { __ } from '@wordpress/i18n';
import type { KeyboardEvent } from 'react';
import { SIDEBAR_CONSTANTS } from '../../hooks/useSidebarState';
import type { SidebarTab } from '../../types';
import Inspector from './Inspector';
import TemplateSettings from './TemplateSettings';

interface SidebarHeaderProps {
  activeTab: SidebarTab;
  onTabChange: (tab: SidebarTab) => void;
}

interface SidebarContentProps {
  activeTab: SidebarTab;
  postType: string;
  postId: number;
}

const tabs = [
  {
    id: SIDEBAR_CONSTANTS.TABS.TEMPLATE,
    label: __('Document', 'campaignbridge'),
  },
  {
    id: SIDEBAR_CONSTANTS.TABS.INSPECTOR,
    label: __('Block', 'campaignbridge'),
  },
] satisfies Array<{ id: SidebarTab; label: string }>;

/** Controlled, accessible tabs for the primary editor sidebar. */
export function SidebarHeader({
  activeTab,
  onTabChange,
}: SidebarHeaderProps): JSX.Element {
  const handleKeyDown = (
    event: KeyboardEvent<HTMLButtonElement>,
    index: number
  ) => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
      return;
    }

    event.preventDefault();
    const nextIndex =
      event.key === 'Home'
        ? 0
        : event.key === 'End'
          ? tabs.length - 1
          : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) %
            tabs.length;
    const nextTab = tabs[nextIndex];

    onTabChange(nextTab.id);
    document.getElementById(`cb-sidebar-tab-${nextTab.id}`)?.focus();
  };

  return (
    <div
      className='components-tab-panel__tabs cb-editor__sidebar-tabs'
      role='tablist'
      aria-label={__('Sidebar tabs', 'campaignbridge')}
    >
      {tabs.map((tab, index) => {
        const selected = activeTab === tab.id;

        return (
          <button
            key={tab.id}
            type='button'
            role='tab'
            className={
              selected
                ? 'components-tab-panel__tabs-item is-active'
                : 'components-tab-panel__tabs-item'
            }
            onClick={() => onTabChange(tab.id)}
            onKeyDown={event => handleKeyDown(event, index)}
            aria-selected={selected}
            aria-controls='cb-sidebar-content'
            id={`cb-sidebar-tab-${tab.id}`}
            tabIndex={selected ? 0 : -1}
          >
            {tab.label}
          </button>
        );
      })}
    </div>
  );
}

/** Render the content for the active primary-sidebar tab. */
export function SidebarContent({
  activeTab,
  postType,
  postId,
}: SidebarContentProps): JSX.Element {
  return (
    <div
      id='cb-sidebar-content'
      role='tabpanel'
      aria-labelledby={`cb-sidebar-tab-${activeTab}`}
    >
      {activeTab === SIDEBAR_CONSTANTS.TABS.TEMPLATE ? (
        <TemplateSettings postType={postType} postId={postId} />
      ) : (
        <Inspector />
      )}
    </div>
  );
}
