import contract from '../../../includes/Email_Blocks/email-blocks.json';

/** One authored Brand Kit binding and its compiler attribute target. */
export interface BrandBindingRule {
  field: string;
  target: string;
}

/** The `brandBindings` section of the email block contract. */
interface BrandBindingContract {
  source: string;
  attributes: Record<string, Record<string, BrandBindingRule>>;
}

const BRAND_BINDINGS = contract.brandBindings as BrandBindingContract;

/** The one supported frozen Brand Kit binding source. */
export const BRAND_BINDING_SOURCE = BRAND_BINDINGS.source;

/** Brand Kit fields accepted for each Core block attribute. */
export const BRAND_BINDING_ATTRIBUTES = BRAND_BINDINGS.attributes;

/** Build one saved Brand Kit binding from the packaged contract. */
export function brandBinding(
  blockName: string,
  attribute: string
): { source: string; args: { field: string } } {
  const rule = BRAND_BINDING_ATTRIBUTES[blockName]?.[attribute];
  if (!rule) {
    throw new Error(`Unsupported Brand Kit binding: ${blockName}.${attribute}`);
  }

  return {
    source: BRAND_BINDING_SOURCE,
    args: { field: rule.field },
  };
}
