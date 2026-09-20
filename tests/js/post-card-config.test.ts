import {
  POST_CARD_ALLOWED_BLOCKS,
  POST_CARD_TEMPLATE,
} from '../../src/blocks/post-card/config';
import { POST_BINDING_SOURCE } from '../../src/blocks/shared/post-bindings';

describe('post-card block configuration', () => {
  it('starts a new card as the stacked post composition', () => {
    expect(POST_CARD_TEMPLATE.map(([name]) => name)).toEqual([
      'campaignbridge/post-image',
      'core/heading',
      'core/paragraph',
      'core/buttons',
    ]);
  });

  it('binds the seeded Core blocks read-only to the selected post', () => {
    const [, heading] = POST_CARD_TEMPLATE[1] as [string, Record<string, any>];
    expect(heading.metadata.bindings.content).toEqual({
      source: POST_BINDING_SOURCE,
      args: { field: 'title' },
    });

    const [, paragraph] = POST_CARD_TEMPLATE[2] as [
      string,
      Record<string, any>,
    ];
    expect(paragraph.metadata.bindings.content).toEqual({
      source: POST_BINDING_SOURCE,
      args: { field: 'excerpt', maxWords: 50 },
    });

    const [, , buttons] = POST_CARD_TEMPLATE[3] as [string, unknown, any[]];
    expect(buttons[0][1].metadata.bindings.url).toEqual({
      source: POST_BINDING_SOURCE,
      args: { field: 'url' },
    });
    // The label stays authored: only the destination comes from the post.
    expect(buttons[0][1].text).toBe('Read more');
  });

  it('only seeds blocks the card actually accepts', () => {
    for (const [name] of POST_CARD_TEMPLATE) {
      expect(POST_CARD_ALLOWED_BLOCKS).toContain(name);
    }
  });

  it('accepts columns for layout without seeding an empty one', () => {
    // Columns is opt-in: deriving the template from the allowed list would
    // drop an empty columns block into every new card.
    expect(POST_CARD_ALLOWED_BLOCKS).toContain('campaignbridge/columns');
    expect(POST_CARD_TEMPLATE).not.toContainEqual(['campaignbridge/columns']);
  });
});
