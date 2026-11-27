import Extend from "flarum/common/extenders";
import app from "flarum/admin/app";
import type ExtensionPage from "flarum/admin/components/ExtensionPage";

import AutomaticSyncSettings from "./components/AutomaticSyncSettings";
import BundesligaAtSettingsInfo from "./components/BundesligaAtSettingsInfo";
import SquadSyncSettings from "./components/SquadSyncSettings";
import TeamSyncSettings from "./components/TeamSyncSettings";

export default [
  new Extend.Admin()
    .permission(
      () => ({
        permission: "wss-lineup.createLineup",
        icon: "fas fa-users",
        label: app.translator.trans(
          "m4v3rick4git-lineup.admin.permissions.create_lineup_label",
          {},
          true,
        ),
      }),
      "start",
      50,
    )
    .setting(
      () =>
        function () {
          return BundesligaAtSettingsInfo.component();
        },
      100,
    )
    .setting(
      () =>
        function () {
          return TeamSyncSettings.component();
        },
      -100,
    )
    .setting(
      () =>
        function (this: ExtensionPage) {
          return AutomaticSyncSettings.component({
            page: this,
            target: "teams",
          });
        },
      -110,
    )
    .setting(
      () =>
        function () {
          return SquadSyncSettings.component();
        },
      -200,
    )
    .setting(
      () =>
        function (this: ExtensionPage) {
          return AutomaticSyncSettings.component({
            page: this,
            target: "squads",
          });
        },
      -210,
    ),
];
