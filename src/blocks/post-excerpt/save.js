import { InnerBlocks, useBlockProps } from "@wordpress/block-editor";

/**
 * Save function for post-excerpt block.
 *
 * @param {Object} props - Block properties
 * @param {Object} props.attributes - Block attributes
 * @returns {JSX.Element} Block save element
 */
export default function Save() {
  return (
    <div {...useBlockProps.save()}>
      <InnerBlocks.Content />
    </div>
  );
}
