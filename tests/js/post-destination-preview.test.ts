import {
  isSafePreviewUrl,
  normalizeDestination,
  resolveDestinationPreview,
} from '../../src/blocks/shared/post-destination';

describe('resolveDestinationPreview', () => {
  describe('previewUrl resolution', () => {
    it('returns customUrl when destination is custom', () => {
      const result = resolveDestinationPreview({
        destination: 'custom',
        customUrl: 'https://example.com/custom',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'https://example.com/parent',
        postTypeArchiveUrl: 'https://example.com/archive',
      });

      expect(result.previewUrl).toBe('https://example.com/custom');
      expect(result.destinationHelp).toBe('');
    });

    it('returns postParentUrl when destination is postParent and safe', () => {
      const result = resolveDestinationPreview({
        destination: 'postParent',
        customUrl: 'https://example.com/custom',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'https://example.com/parent',
        postTypeArchiveUrl: 'https://example.com/archive',
      });

      expect(result.previewUrl).toBe('https://example.com/parent');
      expect(result.destinationHelp).toBe('');
    });

    it('returns postTypeArchiveUrl when destination is postTypeArchive and safe', () => {
      const result = resolveDestinationPreview({
        destination: 'postTypeArchive',
        customUrl: 'https://example.com/custom',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'https://example.com/parent',
        postTypeArchiveUrl: 'https://example.com/archive',
      });

      expect(result.previewUrl).toBe('https://example.com/archive');
      expect(result.destinationHelp).toBe('');
    });

    it('returns articleUrl when destination is article and safe', () => {
      const result = resolveDestinationPreview({
        destination: 'article',
        customUrl: 'https://example.com/custom',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'https://example.com/parent',
        postTypeArchiveUrl: 'https://example.com/archive',
      });

      expect(result.previewUrl).toBe('https://example.com/article');
      expect(result.destinationHelp).toBe('');
    });

    it('falls back to articleUrl when destination-specific URL is unsafe', () => {
      const result = resolveDestinationPreview({
        destination: 'postParent',
        customUrl: '',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'javascript:void(0)',
        postTypeArchiveUrl: '',
      });

      expect(result.previewUrl).toBe('https://example.com/article');
      expect(result.destinationHelp).toBe('');
    });

    it('returns empty previewUrl when no candidate is safe', () => {
      const result = resolveDestinationPreview({
        destination: 'article',
        customUrl: '',
        articleUrl: '',
        postParentUrl: '',
        postTypeArchiveUrl: '',
      });

      expect(result.previewUrl).toBe('');
      expect(result.destinationHelp).not.toBe('');
    });
  });

  describe('destinationHelp messages', () => {
    it('uses the custom-URL-required message when destination is custom with no preview', () => {
      const result = resolveDestinationPreview({
        destination: 'custom',
        customUrl: '',
        articleUrl: 'https://example.com/article',
        postParentUrl: '',
        postTypeArchiveUrl: '',
      });

      expect(result.previewUrl).toBe('');
      expect(result.destinationHelp).toBe(
        'Enter a custom HTTPS URL to preview it.'
      );
    });

    it('uses the no-HTTPS-URL message for non-custom destinations', () => {
      const result = resolveDestinationPreview({
        destination: 'article',
        customUrl: '',
        articleUrl: '',
        postParentUrl: '',
        postTypeArchiveUrl: '',
      });

      expect(result.previewUrl).toBe('');
      expect(result.destinationHelp).toBe(
        'This post snapshot has no HTTPS URL yet; the link renders from the post data at send time.'
      );
    });

    it('honours caller-provided help messages', () => {
      const result = resolveDestinationPreview({
        destination: 'custom',
        customUrl: '',
        articleUrl: '',
        postParentUrl: '',
        postTypeArchiveUrl: '',
        helpMessages: {
          customUrlRequired: 'Enter a custom HTTPS URL to preview it.',
        },
      });

      expect(result.previewUrl).toBe('');
      expect(result.destinationHelp).toBe(
        'Enter a custom HTTPS URL to preview it.'
      );
    });
  });

  describe('normalization', () => {
    it('normalizes unknown destination values to article', () => {
      expect(normalizeDestination('bogus')).toBe('article');
      expect(normalizeDestination(undefined)).toBe('article');
      expect(normalizeDestination('article')).toBe('article');
      expect(normalizeDestination('postParent')).toBe('postParent');
    });

    it('isSafePreviewUrl only accepts http/https URLs', () => {
      expect(isSafePreviewUrl('https://example.com')).toBe(true);
      expect(isSafePreviewUrl('http://example.com')).toBe(true);
      expect(isSafePreviewUrl('javascript:void(0)')).toBe(false);
      expect(isSafePreviewUrl('not-a-url')).toBe(false);
      expect(isSafePreviewUrl('')).toBe(false);
    });
  });
});
