import { __ } from '@wordpress/i18n';
import type { Diagnostic } from './types';

/** Compiler diagnostics, errors first, each with the block it refers to. */
export function Diagnostics({
  diagnostics,
}: {
  diagnostics: Diagnostic[];
}): JSX.Element | null {
  if (!diagnostics.length) {
    return null;
  }

  const ordered = [...diagnostics].sort((a, b) =>
    a.severity === b.severity ? 0 : a.severity === 'error' ? -1 : 1
  );

  return (
    <ul className='campaignbridge-campaigns__diagnostics'>
      {ordered.map((diagnostic, index) => (
        <li
          key={`${diagnostic.code}-${diagnostic.path}-${index}`}
          className={`campaignbridge-campaigns__diagnostic campaignbridge-campaigns__diagnostic--${diagnostic.severity}`}
        >
          <strong>
            {diagnostic.severity === 'error'
              ? __('Error', 'campaignbridge')
              : __('Warning', 'campaignbridge')}
            :
          </strong>{' '}
          {diagnostic.message}
          {diagnostic.path && (
            <>
              {' '}
              <code>{diagnostic.path}</code>
            </>
          )}
        </li>
      ))}
    </ul>
  );
}
