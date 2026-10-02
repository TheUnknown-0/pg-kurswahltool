// Standard-Kursangebot: Vorlage für neue Jahrgänge und Angebot ohne Server.
// Der Server liest diese Datei ebenfalls (src/Services/Kursangebot.php) – zwischen den Markierungen muss reines JSON stehen.
// faecher: angebotene Halbjahre je Fach (immer = gesetzlich vorgeschrieben, nicht abwählbar)
// zusatz:  Zusatzkurse (nur solche, die im Kurswahlformular der Schule stehen)
// sport:   Kürzel → Name und angebotene Kurse je Halbjahr Q1–Q4
const KURSANGEBOT_STANDARD = /*JSON*/{
 "faecher": [
  {"id":"de","name":"Deutsch","sems":[1,2,3,4],"immer":true},
  {"id":"en","name":"Englisch","sems":[1,2,3,4]},
  {"id":"fr","name":"Französisch","sems":[1,2,3,4]},
  {"id":"la","name":"Latein","sems":[1,2,3,4]},
  {"id":"sp","name":"Spanisch","sems":[1,2,3,4]},
  {"id":"mu","name":"Musik","sems":[1,2,3,4]},
  {"id":"ku","name":"Bildende Kunst","sems":[1,2,3,4]},
  {"id":"ds","name":"Darstellendes Spiel","sems":[1,2,3,4]},
  {"id":"ge","name":"Geschichte","sems":[1,2,3,4]},
  {"id":"geb","name":"Geschichte bilingual","sems":[1,2,3,4]},
  {"id":"pw","name":"Politikwissenschaft","sems":[1,2,3,4]},
  {"id":"pwb","name":"Politikwissenschaft bilingual","sems":[1,2,3,4]},
  {"id":"geo","name":"Geografie","sems":[1,2,3,4]},
  {"id":"phi","name":"Philosophie","sems":[1,2,3,4]},
  {"id":"psy","name":"Psychologie","sems":[1,2]},
  {"id":"sw","name":"Sozialwissenschaften","sems":[1,2,3,4]},
  {"id":"ma","name":"Mathematik","sems":[1,2,3,4],"immer":true},
  {"id":"ph","name":"Physik","sems":[1,2,3,4]},
  {"id":"ch","name":"Chemie","sems":[1,2,3,4]},
  {"id":"bi","name":"Biologie","sems":[1,2,3,4]},
  {"id":"inf","name":"Informatik","sems":[1,2,3,4]}
 ],
 "zusatz": [
  {"id":"z_ks","name":"Kreatives Schreiben","sems":[1,2,3,4]},
  {"id":"z_toefl","name":"TOEFL","sems":[1,2,3,4]},
  {"id":"z_endeb","name":"Debating (Englisch)","sems":[1,2]},
  {"id":"z_tg","name":"Textiles Gestalten","sems":[1,2,3,4]},
  {"id":"z_chor","name":"Ensemble Chor","sems":[1,2,3,4]},
  {"id":"z_band","name":"Big Band","sems":[1,2,3,4]},
  {"id":"z_sub","name":"Studium und Beruf","sems":[1,2,3,4]},
  {"id":"z_pwdeb","name":"Debating (Politikwissenschaft)","sems":[3,4]},
  {"id":"z_gere","name":"Geschichte und Religion","sems":[1,2,3,4]},
  {"id":"z_rt","name":"Relativitätstheorie","sems":[1,2,3,4]},
  {"id":"z_astro","name":"Astronomie","sems":[1,2,3,4]}
 ],
 "sport": {
  "katalog": {"A1":"Leichtathletik","B1":"Basketball","B3":"Fußball","B4":"Handball","B5":"Hockey","B6":"Rugby","B7":"Volleyball","B8":"Badminton","B10":"Tischtennis","B12":"Ultimate Frisbee","C1":"Geräteturnen","D1":"Gymnastik/Tanz","E1":"Schwimmen","G2":"Rudern","H1":"Fitness"},
  "sems": [
   ["A1","B3","B4","B12","B7","B8","B10","C1","D1","E1","G2","H1"],
   ["A1","B1","B3","B5","B6","B7","B8","B10","D1","E1","G2","H1"],
   ["A1","B3","B4","B12","B7","B8","B10","C1","D1","E1","G2","H1"],
   ["A1","B1","B3","B5","B6","B7","B8","B10","D1","E1","G2","H1"]
  ]
 }
}/*JSON*/;
