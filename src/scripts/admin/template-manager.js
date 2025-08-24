import apiFetch from "@wordpress/api-fetch";
import { Button, Notice, SelectControl, Spinner } from "@wordpress/components";
import { dispatch, select } from "@wordpress/data";
import domReady from "@wordpress/dom-ready";
import {
  createElement,
  createRoot,
  useEffect,
  useState,
} from "@wordpress/element";
import { __ } from "@wordpress/i18n";

const CFG = window.CB_TM || {};
const CPT = CFG.postType || "cb_email_template";

const getParam = (k) => new URLSearchParams(window.location.search).get(k);
const setParamAndReload = (k, v) => {
  const url = new URL(window.location.href);
  if (v == null) url.searchParams.delete(k);
  else url.searchParams.set(k, String(v));
  window.location.replace(url.toString());
};

apiFetch.use(apiFetch.createNonceMiddleware(CFG.nonce));
apiFetch.use(apiFetch.createRootURLMiddleware(CFG.apiRoot));

async function createDraft() {
  const rec = await dispatch("core").saveEntityRecord("postType", CPT, {
    status: "draft",
    title: CFG.defaultTitle || "Untitled",
  });
  return rec?.id;
}

async function fetchTemplates() {
  const cached = select("core").getEntityRecords("postType", CPT, {
    per_page: 100,
    _fields: ["id", "title", "status", "date"],
  });
  if (Array.isArray(cached)) return cached;
  return apiFetch({
    path: `/wp/v2/${CPT}?per_page=100&_fields=id,title,status,date`,
  });
}

function bootCoreEditor(targetId, postId) {
  // This is the same initializer core uses on post.php.
  // Requires wp-edit-post and friends to be enqueued as deps.
  window.wp.editPost.initializeEditor(targetId, CPT, postId);
}

function App() {
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [currentId] = useState(
    Number(CFG.currentPostId || getParam("post_id")) || null,
  );

  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const posts = await fetchTemplates();
        if (!alive) return;
        setList(Array.isArray(posts) ? posts : []);
      } catch (e) {
        setError(e?.message || "Failed to load templates.");
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => (alive = false);
  }, []);

  useEffect(() => {
    let alive = true;
    (async () => {
      let id = currentId;
      try {
        if (!id) {
          id = await createDraft();
          setParamAndReload("post_id", id);
          return;
        }
        if (!alive) return;
        bootCoreEditor("cb-editor-root", id);
      } catch (e) {
        setError(e?.message || "Failed to initialize editor.");
      }
    })();
    return () => (alive = false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onSelect = (val) => {
    const id = Number(val);
    if (id) setParamAndReload("post_id", id);
  };
  const onNew = async () => {
    const id = await createDraft();
    setParamAndReload("post_id", id);
  };

  return createElement(
    "div",
    { className: "cb-tm-shell" },
    error &&
      createElement(Notice, { status: "error", isDismissible: false }, error),
    createElement(
      "div",
      { className: "cb-tm-toolbar" },
      createElement(SelectControl, {
        label: __("Templates", "campaignbridge"),
        value: currentId || "",
        onChange: onSelect,
        disabled: loading || list.length === 0,
        options: [
          ...(currentId
            ? []
            : [{ label: __("Loading…", "campaignbridge"), value: "" }]),
          ...list.map((p) => ({
            label: p?.title?.rendered || `#${p.id}`,
            value: String(p.id),
          })),
        ],
      }),
      createElement(
        Button,
        { variant: "primary", onClick: onNew },
        __("New Template", "campaignbridge"),
      ),
    ),
    loading &&
      createElement(
        "div",
        { className: "cb-tm-loading" },
        createElement(Spinner, null),
      ),
    createElement("div", { id: "cb-editor-root", className: "cb-editor-root" }),
  );
}

domReady(() => {
  const root = document.getElementById("cb-template-manager-root");
  if (root) {
    // Use React 18 createRoot API instead of deprecated render
    const reactRoot = createRoot(root);
    reactRoot.render(createElement(App));
  }
});
