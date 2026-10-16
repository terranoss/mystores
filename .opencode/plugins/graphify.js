// graphify OpenCode plugin (V2 plugin API)
// Injects a knowledge graph reminder before the first bash tool call when the
// graph exists.
//
// IMPORTANT: keep the reminder string free of backticks and $(...) constructs.
// The hook prepends `echo "<reminder>" ; <cmd>` to the user's bash command;
// backticks inside the double-quoted echo trigger shell command substitution,
// which both corrupts tool output and silently executes the very graphify
// command we are only suggesting. Plain words render fine in opencode's TUI.
import { existsSync } from "fs";
import { join } from "path";

const REMINDER =
  '[graphify] knowledge graph at graphify-out/. For focused questions, run graphify query with your question (scoped subgraph, usually much smaller than GRAPH_REPORT.md) instead of grepping raw files. Read GRAPH_REPORT.md only for broad architecture context.';

export default {
  id: "graphify",

  async setup(ctx) {
    let reminded = false;

    if (!existsSync(join(ctx.location.directory, "graphify-out", "graph.json"))) return;

    await ctx.tool.hook("execute.before", (event) => {
      if (reminded) return;
      if (event.tool !== "bash") return;

      // ';' not '&&' — Windows PowerShell 5.1 rejects '&&' as a statement
      // separator, breaking the first bash command of the session (#1646).
      const input = event.input;
      if (!input || typeof input.command !== "string") return;

      input.command = `echo "${REMINDER}" ; ` + input.command;
      reminded = true;
    });
  },
};