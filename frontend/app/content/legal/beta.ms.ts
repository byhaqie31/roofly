import type { LegalDocumentContent } from "./types";

// Terma beta — UAT sahaja (useEnv().showBetaTerms). DRAF — mesti sepadan dengan beta.en.ts.
const doc: LegalDocumentContent = {
  title: "Terma beta",
  summary:
    "Anda sedang menggunakan versi awal Roofly sebagai penguji beta. Terma ini terpakai sebagai tambahan kepada [terma perkhidmatan](/legal/terms) dan [notis privasi](/legal/privacy) kami.",
  sections: [
    {
      id: "as-is",
      heading: "Disediakan seadanya",
      blocks: [
        {
          type: "p",
          text: "Beta disediakan \"seadanya\" dan \"mengikut ketersediaan\", tanpa bayaran. Ciri mungkin berubah, rosak atau dibuang, dan perkhidmatan mungkin tidak tersedia pada masa tertentu. Jangan bergantung pada beta sebagai satu-satunya rekod bagi apa-apa yang penting.",
        },
      ],
    },
    {
      id: "simulated-payments",
      heading: "Bayaran hanyalah simulasi",
      blocks: [
        {
          type: "p",
          text: "Tiada wang sebenar bergerak dalam beta. Membayar invois dalam beta hanya menyimulasikan bayaran, dan status \"dibayar\" ialah rekod ujian. Jangan masukkan butiran kad atau bank sebenar di mana-mana dalam beta.",
        },
      ],
    },
    {
      id: "your-data",
      heading: "Data anda dalam beta",
      blocks: [
        {
          type: "p",
          text: "Data yang anda masukkan dalam beta ialah data peribadi dan dilindungi seperti yang diterangkan dalam notis privasi kami. Kami mungkin perlu membetulkan atau menetapkan semula data ujian semasa membaiki masalah. Jika boleh, kami akan memaklumkan anda sebelum penetapan semula.",
        },
        {
          // TODO(legal): sahkan pelan pemindahan dan sama ada penguji perlu memberi persetujuan.
          type: "p",
          text: "Kami berhasrat memindahkan akaun penguji beta ke perkhidmatan sebenar apabila Roofly dilancarkan. Kami akan memaklumkan anda sebelum melakukannya, dan anda boleh meminta kami tidak memindahkan akaun anda.",
        },
      ],
    },
    {
      id: "feedback",
      heading: "Maklum balas",
      blocks: [
        {
          type: "p",
          text: "Maklum balas anda membentuk Roofly. Apabila anda menghantar maklum balas, laporan pepijat atau idea, melalui Bantuan & maklum balas atau cara lain, kami menyimpannya bersama nama dan e-mel anda supaya kami boleh membuat susulan, dan kami boleh menggunakannya untuk menambah baik Roofly tanpa berhutang apa-apa kepada anda. Kami tidak akan menerbitkan nama atau maklum balas anda tanpa kebenaran anda.",
        },
      ],
    },
    {
      id: "ending",
      heading: "Menamatkan beta",
      blocks: [
        {
          type: "p",
          text: "Kami boleh menamatkan beta, atau akses anda kepadanya, pada bila-bila masa. Anda boleh berhenti mengambil bahagian pada bila-bila masa dengan memaklumkan kami.",
        },
        { type: "contact", channel: "general" },
      ],
    },
  ],
};

export default doc;
