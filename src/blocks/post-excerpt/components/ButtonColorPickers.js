import { ColorPalette } from "@wordpress/components";
import { useEditorSettings } from "../../../scripts/template-editor/hooks/useEditorSettings";

export default function ButtonColorPickers({
  buttonBg,
  buttonColor,
  onButtonBgChange,
  onButtonColorChange,
}) {
  const { settings } = useEditorSettings();

  return (
    <>
      <div style={{ marginBottom: "16px" }}>
        <label
          style={{
            display: "block",
            marginBottom: "8px",
            fontWeight: "bold",
          }}
        >
          Button Background
        </label>
        <ColorPalette
          colors={settings?.colors || []}
          value={buttonBg}
          onChange={onButtonBgChange}
          disableCustomColors={false}
          clearable={true}
        />
      </div>

      <div style={{ marginBottom: "16px" }}>
        <label
          style={{
            display: "block",
            marginBottom: "8px",
            fontWeight: "bold",
          }}
        >
          Button Text Color
        </label>
        <ColorPalette
          colors={settings?.colors || []}
          value={buttonColor}
          onChange={onButtonColorChange}
          disableCustomColors={false}
          clearable={true}
        />
      </div>
    </>
  );
}
