import {
  buildDuplicatePayload,
  parseDuplicableMetaKeys,
  UNTITLED_TEMPLATE_TITLE,
} from '../../src/scripts/editor/utils/templateDuplication';

const ALLOWED = ['campaignbridge_subject', 'campaignbridge_utm_enabled'];

describe('parseDuplicableMetaKeys', () => {
  it('reads a JSON array of meta keys', () => {
    expect(parseDuplicableMetaKeys(JSON.stringify(ALLOWED))).toEqual(ALLOWED);
    expect(parseDuplicableMetaKeys('[]')).toEqual([]);
  });

  it.each([
    ['missing', undefined],
    ['empty', ''],
    ['malformed', '[campaignbridge_subject'],
    ['not an array', '{"campaignbridge_subject":true}'],
    ['non-string keys', '["campaignbridge_subject",1]'],
  ])('fails closed for %s data', (_label, value) => {
    expect(parseDuplicableMetaKeys(value)).toBeNull();
  });
});

describe('buildDuplicatePayload', () => {
  const saved = {
    id: 42,
    status: 'publish',
    date: '2026-09-13T10:00:00',
    author: 3,
    slug: 'launch',
    title: 'Launch',
    content: '<!-- wp:campaignbridge/container /-->',
    meta: {
      campaignbridge_subject: 'Launch subject',
      campaignbridge_utm_enabled: false,
      campaignbridge_audience_tags: 'launch-list',
      campaignbridge_provider_campaign_id: 'remote-1',
    },
  };

  it('copies only the draft, title, content, and allowlisted meta', () => {
    expect(buildDuplicatePayload(saved, ALLOWED)).toEqual({
      status: 'draft',
      title: 'Launch (Copy)',
      content: saved.content,
      meta: {
        campaignbridge_subject: 'Launch subject',
        campaignbridge_utm_enabled: false,
      },
    });
  });

  it('copies no meta when nothing is allowlisted', () => {
    expect(buildDuplicatePayload(saved, []).meta).toEqual({});
  });

  it('omits allowlisted keys the saved template does not have', () => {
    expect(
      buildDuplicatePayload({ title: 'Launch', content: '', meta: {} }, ALLOWED)
        .meta
    ).toEqual({});
    expect(buildDuplicatePayload({ title: 'Launch' }, ALLOWED).meta).toEqual(
      {}
    );
  });

  it('reads raw title and content from REST-shaped values', () => {
    const payload = buildDuplicatePayload(
      { title: { raw: 'Launch' }, content: { raw: 'Saved content' } },
      ALLOWED
    );

    expect(payload.title).toBe('Launch (Copy)');
    expect(payload.content).toBe('Saved content');
  });

  it.each([
    ['empty', ''],
    ['whitespace', '   '],
    ['missing', undefined],
  ])(
    'titles a copy of an %s title with the untitled convention',
    (_l, title) => {
      expect(buildDuplicatePayload({ title }, ALLOWED).title).toBe(
        `${UNTITLED_TEMPLATE_TITLE} (Copy)`
      );
    }
  );

  it('appends another suffix to a copy of a copy', () => {
    expect(buildDuplicatePayload({ title: 'Launch (Copy)' }, []).title).toBe(
      'Launch (Copy) (Copy)'
    );
  });

  it('never shares or mutates the saved meta object', () => {
    const before = JSON.parse(JSON.stringify(saved));
    const payload = buildDuplicatePayload(saved, ALLOWED);
    payload.meta.campaignbridge_subject = 'Changed on the copy';

    expect(payload.meta).not.toBe(saved.meta);
    expect(saved).toEqual(before);
  });
});
