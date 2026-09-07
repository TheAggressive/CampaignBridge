import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { search } from '@wordpress/icons';

interface PreviewButtonProps {
  onClick: () => void;
}

/**
 * The Preview button shown in the editor header, to the left of Save.
 * Clicking it opens the compiled email preview modal.
 */
export default function PreviewButton({
  onClick,
}: PreviewButtonProps): JSX.Element {
  return (
    <Button
      variant='secondary'
      icon={search}
      onClick={onClick}
      className='cb-editor__preview-button'
    >
      {__('Preview', 'campaignbridge')}
    </Button>
  );
}
