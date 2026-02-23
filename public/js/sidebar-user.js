(() => {
  const wrap = document.getElementById("sbUser");
  if (!wrap) return;

  const btn = document.getElementById("sbUserBtn");

  const close = () => {
    wrap.classList.remove("is-open");
    btn?.setAttribute("aria-expanded", "false");
  };

  btn?.addEventListener("click", (e) => {
    e.preventDefault();
    const open = wrap.classList.toggle("is-open");
    btn.setAttribute("aria-expanded", open ? "true" : "false");
  });

  document.addEventListener("click", (e) => {
    if (!wrap.contains(e.target)) close();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") close();
  });
})();