<?php

namespace Database\Seeders;

use App\Models\CatalogMedicine;
use App\Models\DrugInteraction;
use App\Models\MedicineCategory;
use App\Models\OtcRule;
use Illuminate\Database\Seeder;

/**
 * Sample reference catalog of medicines commonly used in the Philippines.
 *
 * The wording is general consumer-level guidance summarised from standard
 * drug references (e.g. WHO Model List of Essential Medicines, Philippine
 * National Formulary, manufacturer leaflets). It is a starting dataset for the
 * prototype: have a licensed pharmacist review and correct it before the pilot.
 * Everything shown to users is guidance only.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->medicines() as $m) {
            [$generic, $brands, $category, $form, $strength, $purpose, $how, $warnings, $otc] = $m;

            CatalogMedicine::updateOrCreate(['generic_name' => $generic], [
                'brand_names' => $brands,
                'category_id' => MedicineCategory::firstOrCreate(['name' => $category])->id,
                'form' => $form,
                'strength' => $strength,
                'purpose' => $purpose,
                'how_to_take' => $how,
                'warnings' => $warnings,
                'is_otc' => $otc,
            ]);
        }

        foreach ($this->interactions() as [$a, $b, $severity, $note]) {
            $idA = CatalogMedicine::where('generic_name', $a)->value('id');
            $idB = CatalogMedicine::where('generic_name', $b)->value('id');
            if (! $idA || ! $idB) {
                continue;
            }
            [$idA, $idB] = $idA < $idB ? [$idA, $idB] : [$idB, $idA];

            DrugInteraction::updateOrCreate(
                ['medicine_a_id' => $idA, 'medicine_b_id' => $idB],
                ['severity' => $severity, 'note' => $note],
            );
        }

        foreach ($this->otcRules() as [$symptom, $keywords, $generic, $priority, $notes, $seeDoctorIf]) {
            $id = CatalogMedicine::where('generic_name', $generic)->value('id');
            if (! $id) {
                continue;
            }

            OtcRule::updateOrCreate(
                ['symptom' => $symptom, 'catalog_medicine_id' => $id],
                ['keywords' => $keywords, 'priority' => $priority, 'notes' => $notes, 'see_doctor_if' => $seeDoctorIf],
            );
        }
    }

    /**
     * Everyday complaints and the non-prescription medicines commonly suggested for them.
     * [symptom, keywords, generic name, priority (1 = first choice), tip, see a doctor if]
     */
    private function otcRules(): array
    {
        $fever = 'Fever lasts more than 3 days, is above 39.5 C, or comes with a stiff neck, rash, trouble breathing or confusion. Always see a doctor for fever in babies under 3 months.';
        $pain = 'Pain is severe, getting worse, lasts more than a few days, or follows an injury.';

        return [
            ['Headache', 'head,migraine,headache', 'Paracetamol', 1, 'Usually the gentlest first choice for headache.', 'Headache is sudden and severe, follows a head injury, or comes with fever, stiff neck, weakness or vision changes.'],
            ['Headache', 'head,migraine,headache', 'Ibuprofen', 2, 'Take with food. Not for people with stomach ulcers or kidney problems.', 'Headache is sudden and severe, follows a head injury, or comes with fever, stiff neck, weakness or vision changes.'],
            ['Fever', 'fever,lagnat,temperature,hot', 'Paracetamol', 1, 'Lowers fever. Follow the label dose and the time between doses.', $fever],
            ['Fever', 'fever,lagnat,temperature,hot', 'Ibuprofen', 2, 'Take with food. Drink plenty of fluids.', $fever],
            ['Body aches and muscle pain', 'body ache,muscle,sore,pain,sakit', 'Paracetamol', 1, 'A good first choice for mild aches.', $pain],
            ['Body aches and muscle pain', 'body ache,muscle,sore,pain,sakit', 'Ibuprofen', 2, 'Helps with swelling as well. Take with food.', $pain],
            ['Toothache', 'tooth,dental,ngipin', 'Paracetamol', 1, 'Eases pain for a short time until you see a dentist.', 'Swelling of the face or jaw, fever, or pain that lasts more than a day or two. See a dentist.'],
            ['Toothache', 'tooth,dental,ngipin', 'Ibuprofen', 2, 'Take with food. Short-term use only.', 'Swelling of the face or jaw, fever, or pain that lasts more than a day or two. See a dentist.'],
            ['Menstrual cramps', 'period,menstrual,cramp,dysmenorrhea', 'Ibuprofen', 1, 'Works well for cramps when taken at the start of pain, with food.', 'Pain is severe or new, periods are very heavy, or you may be pregnant.'],
            ['Menstrual cramps', 'period,menstrual,cramp,dysmenorrhea', 'Paracetamol', 2, 'A gentler option if you cannot take ibuprofen.', 'Pain is severe or new, periods are very heavy, or you may be pregnant.'],
            ['Colds and flu', 'cold,flu,sipon,stuffy,nose,sneez,trangkaso', 'Paracetamol + Phenylephrine + Chlorphenamine', 1, 'Covers fever, stuffy nose and sneezing in one tablet. May cause drowsiness.', 'Symptoms last more than 7 days, you have trouble breathing, chest pain, or a high fever that will not go down.'],
            ['Colds and flu', 'cold,flu,sipon,stuffy,nose,sneez,trangkaso', 'Paracetamol', 2, 'Use alone if you only have fever or aches. Do not combine with the cold tablet.', 'Symptoms last more than 7 days, you have trouble breathing, chest pain, or a high fever that will not go down.'],
            ['Dry cough', 'dry cough,cough,ubo,tickle', 'Dextromethorphan', 1, 'Eases a dry, irritating cough. Do not use it for a cough with phlegm.', 'Cough lasts more than 2 weeks, brings up blood, or comes with chest pain, wheezing or shortness of breath.'],
            ['Cough with phlegm', 'phlegm,plema,productive,cough,ubo,mucus', 'Carbocisteine', 1, 'Thins phlegm so it is easier to cough up. Drink plenty of water.', 'Cough lasts more than 2 weeks, brings up blood, or comes with chest pain, wheezing or shortness of breath.'],
            ['Cough with phlegm', 'phlegm,plema,productive,cough,ubo,mucus', 'Ambroxol', 2, 'Another option that loosens phlegm.', 'Cough lasts more than 2 weeks, brings up blood, or comes with chest pain, wheezing or shortness of breath.'],
            ['Allergy, itching and runny nose', 'allergy,allergic,itch,hives,runny,sneez,kati,pantal', 'Cetirizine', 1, 'Once a day. May cause drowsiness in some people.', 'Swelling of the lips, tongue or throat, or trouble breathing. Get emergency help right away.'],
            ['Allergy, itching and runny nose', 'allergy,allergic,itch,hives,runny,sneez,kati,pantal', 'Loratadine', 2, 'Less likely to cause drowsiness.', 'Swelling of the lips, tongue or throat, or trouble breathing. Get emergency help right away.'],
            ['Allergy, itching and runny nose', 'allergy,allergic,itch,hives,runny,sneez,kati,pantal', 'Diphenhydramine', 3, 'Causes drowsiness. Not for driving. Not advised for older adults.', 'Swelling of the lips, tongue or throat, or trouble breathing. Get emergency help right away.'],
            ['Diarrhea', 'diarrhea,loose,lbm,pagtatae,stool', 'Oral rehydration salts', 1, 'The most important treatment: replaces lost water and salts. Sip often.', 'Blood in the stool, high fever, signs of dehydration (very dry mouth, no urine, dizziness), or diarrhea lasting more than 2 days. See a doctor sooner for babies and older adults.'],
            ['Diarrhea', 'diarrhea,loose,lbm,pagtatae,stool', 'Loperamide', 2, 'Slows the diarrhea for adults. Use together with fluids, not instead of them.', 'Blood in the stool, high fever, signs of dehydration (very dry mouth, no urine, dizziness), or diarrhea lasting more than 2 days. See a doctor sooner for babies and older adults.'],
            ['Heartburn and acid indigestion', 'heartburn,acid,indigestion,hyperacidity,sikmura,ulcer', 'Aluminum + Magnesium hydroxide', 1, 'Quick relief. Take other medicines 2 hours apart.', 'Heartburn more than twice a week, trouble swallowing, black stools, vomiting blood, chest pain or unexplained weight loss.'],
            ['Gas and bloating', 'gas,bloat,kabag,flatulence,full', 'Simethicone', 1, 'Chew after meals to ease trapped gas.', 'Severe or constant belly pain, vomiting, or bloating that does not go away.'],
        ];
    }

    /** [generic, brands, category, form, strength, purpose, how to take, warnings, is OTC] */
    private function medicines(): array
    {
        $pain = 'Pain and Fever';
        $bp = 'Blood Pressure';
        $dm = 'Diabetes';
        $chol = 'Cholesterol';
        $abx = 'Antibiotics';
        $allergy = 'Allergy';
        $cold = 'Cough and Cold';
        $gi = 'Stomach and Digestion';
        $resp = 'Asthma and Breathing';
        $vit = 'Vitamins and Supplements';
        $other = 'Other';

        $nsaid = 'Do not use if you have a stomach ulcer, kidney problems, or are allergic to NSAIDs or aspirin. Can irritate the stomach. Avoid taking with other NSAID pain relievers. Ask your doctor first if pregnant.';

        return [
            ['Paracetamol', 'Biogesic, Tempra, Calpol', $pain, 'tablet', '500 mg', 'Relieves mild to moderate pain and lowers fever.', 'Take with water, every 4 to 6 hours as needed. Do not exceed the daily maximum on the label.', 'Too much paracetamol can seriously damage the liver. Check other cold or flu medicines for hidden paracetamol. Avoid alcohol.', true],
            ['Ibuprofen', 'Advil, Medicol', $pain, 'capsule', '200 mg', 'Relieves pain, fever and inflammation.', 'Take with food or milk, every 6 to 8 hours as needed.', $nsaid, true],
            ['Mefenamic acid', 'Ponstan, Dolfenal', $pain, 'capsule', '500 mg', 'Relieves pain, including menstrual cramps and toothache.', 'Take with food, every 6 to 8 hours as needed, for a short time only.', $nsaid, false],
            ['Naproxen sodium', 'Flanax', $pain, 'tablet', '550 mg', 'Relieves pain and inflammation.', 'Take with food, usually every 12 hours as directed.', $nsaid, false],
            ['Aspirin', 'Aspilets', $pain, 'tablet', '80 mg', 'Low dose is used to help prevent blood clots as advised by a doctor.', 'Take once daily after a meal, as directed by your doctor.', 'Increases bleeding risk. Do not give to children with fever. Do not start or stop without your doctor.', false],

            ['Amlodipine', 'Norvasc, Amlogen', $bp, 'tablet', '5 mg', 'Lowers high blood pressure and helps prevent chest pain.', 'Take once a day at the same time, with or without food.', 'May cause ankle swelling, flushing or dizziness. Do not stop suddenly without advice.', false],
            ['Losartan', 'Cozaar, Lifezar', $bp, 'tablet', '50 mg', 'Lowers high blood pressure and protects the kidneys in some patients.', 'Take once a day at the same time, with or without food.', 'Not for use in pregnancy. May cause dizziness. Avoid potassium supplements unless your doctor advises.', false],
            ['Telmisartan', 'Micardis', $bp, 'tablet', '40 mg', 'Lowers high blood pressure.', 'Take once a day at the same time.', 'Not for use in pregnancy. May cause dizziness.', false],
            ['Metoprolol', 'Betaloc, Lopresor', $bp, 'tablet', '50 mg', 'Lowers blood pressure and slows the heart rate.', 'Take with or right after food, as directed.', 'Do not stop suddenly. May cause tiredness or a slow pulse. Tell your doctor if you have asthma.', false],
            ['Atenolol', 'Tenormin', $bp, 'tablet', '50 mg', 'Lowers blood pressure and slows the heart rate.', 'Take once a day at the same time.', 'Do not stop suddenly. May cause tiredness or a slow pulse. Tell your doctor if you have asthma.', false],
            ['Hydrochlorothiazide', 'Hydrite', $bp, 'tablet', '25 mg', 'A water pill that lowers blood pressure.', 'Take in the morning so you are not up at night passing urine.', 'May cause dizziness and low potassium. Drink fluids as advised.', false],

            ['Metformin', 'Glucophage, Diabex', $dm, 'tablet', '500 mg', 'Lowers blood sugar in type 2 diabetes.', 'Take with or right after meals to reduce stomach upset.', 'May cause nausea or loose stools at first. Tell your doctor before any scan with contrast dye or if you are very sick or dehydrated.', false],
            ['Gliclazide', 'Diamicron', $dm, 'tablet', '80 mg', 'Lowers blood sugar in type 2 diabetes.', 'Take with breakfast as directed.', 'Can cause low blood sugar. Do not skip meals. Know the signs: shaking, sweating, confusion.', false],
            ['Glimepiride', 'Amaryl', $dm, 'tablet', '2 mg', 'Lowers blood sugar in type 2 diabetes.', 'Take with the first main meal of the day.', 'Can cause low blood sugar. Do not skip meals.', false],

            ['Atorvastatin', 'Lipitor, Atopitar', $chol, 'tablet', '20 mg', 'Lowers cholesterol to reduce the risk of heart attack and stroke.', 'Take once a day, at any time, at the same time each day.', 'Report unexplained muscle pain or weakness. Avoid large amounts of grapefruit juice.', false],
            ['Simvastatin', 'Zocor', $chol, 'tablet', '20 mg', 'Lowers cholesterol.', 'Take once a day in the evening.', 'Report unexplained muscle pain or weakness. Avoid large amounts of grapefruit juice.', false],
            ['Rosuvastatin', 'Crestor', $chol, 'tablet', '10 mg', 'Lowers cholesterol.', 'Take once a day at the same time.', 'Report unexplained muscle pain or weakness.', false],

            ['Amoxicillin', 'Amoxil', $abx, 'capsule', '500 mg', 'Treats certain bacterial infections.', 'Take at evenly spaced times and finish the full course even if you feel better.', 'Do not use if allergic to penicillin. Stop and seek help for rash, swelling or trouble breathing.', false],
            ['Co-amoxiclav', 'Augmentin', $abx, 'tablet', '625 mg', 'Treats bacterial infections that need a stronger antibiotic.', 'Take at the start of a meal. Finish the full course.', 'Do not use if allergic to penicillin. May cause loose stools.', false],
            ['Azithromycin', 'Zithromax', $abx, 'tablet', '500 mg', 'Treats certain bacterial infections.', 'Take once a day as directed. Finish the full course.', 'Tell your doctor if you have heart rhythm problems. Stop and seek help for rash or swelling.', false],
            ['Cefalexin', 'Keflex', $abx, 'capsule', '500 mg', 'Treats skin, throat and urinary infections caused by bacteria.', 'Take at evenly spaced times. Finish the full course.', 'Tell your doctor if you are allergic to penicillin.', false],
            ['Ciprofloxacin', 'Ciprobay', $abx, 'tablet', '500 mg', 'Treats certain bacterial infections.', 'Take with water. Finish the full course.', 'Report tendon pain or swelling right away. Take 2 hours before or 6 hours after antacids, iron or calcium.', false],
            ['Cotrimoxazole', 'Bactrim, Septrin', $abx, 'tablet', '800/160 mg', 'Treats urinary and some other bacterial infections.', 'Take with plenty of water. Finish the full course.', 'Stop and seek help for rash. Not for people allergic to sulfa drugs.', false],

            ['Cetirizine', 'Virlix, Zyrtec', $allergy, 'tablet', '10 mg', 'Relieves allergy symptoms such as sneezing, itching and runny nose.', 'Take once a day.', 'May cause drowsiness in some people. Be careful when driving.', true],
            ['Loratadine', 'Claritin, Loraxin', $allergy, 'tablet', '10 mg', 'Relieves allergy symptoms with less drowsiness.', 'Take once a day.', 'May still cause drowsiness in some people.', true],
            ['Diphenhydramine', 'Benadryl', $allergy, 'capsule', '25 mg', 'Relieves allergy symptoms and itching.', 'Take every 6 to 8 hours as needed.', 'Causes drowsiness. Do not drive. Avoid alcohol. Not for older adults without advice.', true],

            ['Carbocisteine', 'Solmux', $cold, 'capsule', '500 mg', 'Loosens thick phlegm in productive cough.', 'Take 3 times a day with water as directed.', 'Tell your doctor if you have a stomach ulcer.', true],
            ['Ambroxol', 'Mucosolvan', $cold, 'tablet', '30 mg', 'Loosens phlegm and makes it easier to cough up.', 'Take 2 to 3 times a day after meals.', 'Stop and seek help for rash or swelling.', true],
            ['Dextromethorphan', 'Robitussin DM', $cold, 'syrup', '15 mg/5 mL', 'Eases dry, irritating cough.', 'Measure with the supplied cup every 6 to 8 hours as needed.', 'Not for children under 6. May cause drowsiness. Do not combine with other cough products.', true],
            ['Paracetamol + Phenylephrine + Chlorphenamine', 'Neozep, Bioflu', $cold, 'tablet', '500/10/2 mg', 'Relieves cold and flu symptoms: fever, stuffy nose, sneezing.', 'Take every 6 hours as needed for up to a few days.', 'Contains paracetamol, so do not take other paracetamol products. May cause drowsiness. Use with care if you have high blood pressure.', true],

            ['Omeprazole', 'Losec, Omepron', $gi, 'capsule', '20 mg', 'Reduces stomach acid for heartburn, reflux and ulcers.', 'Take 30 minutes before a meal, usually in the morning.', 'See a doctor if symptoms last more than two weeks or you lose weight or have black stools.', false],
            ['Loperamide', 'Imodium', $gi, 'capsule', '2 mg', 'Slows down short-term diarrhea.', 'Take after each loose stool up to the daily maximum on the label.', 'Do not use if there is blood in the stool or high fever. Drink plenty of fluids. Not for young children.', true],
            ['Oral rehydration salts', 'Hydrite, Pedialyte', $gi, 'sachet', '1 sachet', 'Replaces water and salts lost with diarrhea or vomiting.', 'Mix with the exact amount of clean water on the label and sip often.', 'Seek care if a child or older adult cannot keep fluids down.', true],
            ['Simethicone', 'Kremil-S, Gas-X', $gi, 'tablet', '80 mg', 'Relieves gas and bloating.', 'Chew after meals and at bedtime as needed.', 'Generally safe. See a doctor if pain is severe or lasts.', true],
            ['Aluminum + Magnesium hydroxide', 'Maalox, Kremil-S', $gi, 'tablet', '400/400 mg', 'Relieves heartburn and acid indigestion.', 'Chew or take as directed, usually one hour after meals and at bedtime.', 'Can affect how other medicines are absorbed; take other medicines 2 hours apart. Tell your doctor if you have kidney disease.', true],

            ['Salbutamol', 'Ventolin', $resp, 'inhaler', '100 mcg per puff', 'Quick relief of wheezing and shortness of breath in asthma.', 'Shake and inhale as directed. Wait a minute between puffs.', 'Seek urgent care if it does not help or you need it more often than usual. May cause shaking or fast heartbeat.', false],

            ['Vitamin C', 'Cecon, Ascorbic acid', $vit, 'tablet', '500 mg', 'Vitamin supplement.', 'Take once a day with water.', 'High doses may upset the stomach.', true],
            ['Ferrous sulfate', 'Ferrous sulfate, Sangobion', $vit, 'tablet', '325 mg', 'Iron supplement for low iron or anemia.', 'Take as directed, ideally on an empty stomach or with vitamin C if it does not upset the stomach.', 'May cause dark stools and constipation. Keep away from children. Separate from antacids and some antibiotics.', true],
            ['Multivitamins', 'Enervon, Centrum', $vit, 'tablet', '1 tablet', 'Daily vitamin and mineral supplement.', 'Take once a day after a meal.', 'Do not exceed the stated dose.', true],

            ['Levothyroxine', 'Euthyrox, Eltroxin', $other, 'tablet', '50 mcg', 'Replaces thyroid hormone for an underactive thyroid.', 'Take once a day on an empty stomach, 30 to 60 minutes before breakfast.', 'Take at the same time every day. Separate from iron and calcium by 4 hours.', false],
            ['Allopurinol', 'Zyloric', $other, 'tablet', '100 mg', 'Lowers uric acid to prevent gout attacks.', 'Take after a meal with plenty of water.', 'Stop and seek help for rash. Do not start during an acute gout attack unless told.', false],
        ];
    }

    /** [medicine A, medicine B, severity, note] */
    private function interactions(): array
    {
        return [
            ['Ibuprofen', 'Aspirin', 'moderate', 'Ibuprofen may reduce the heart-protecting effect of low-dose aspirin and raises the risk of stomach bleeding.'],
            ['Ibuprofen', 'Naproxen sodium', 'high', 'Two NSAID pain relievers together raise the risk of stomach bleeding and kidney problems without extra relief.'],
            ['Ibuprofen', 'Mefenamic acid', 'high', 'Two NSAID pain relievers together raise the risk of stomach bleeding and kidney problems without extra relief.'],
            ['Ibuprofen', 'Losartan', 'moderate', 'NSAIDs can weaken the blood-pressure effect and may strain the kidneys.'],
            ['Mefenamic acid', 'Losartan', 'moderate', 'NSAIDs can weaken the blood-pressure effect and may strain the kidneys.'],
            ['Simvastatin', 'Amlodipine', 'moderate', 'Amlodipine can raise simvastatin levels and the risk of muscle problems. Your doctor may limit the simvastatin dose.'],
            ['Atenolol', 'Metoprolol', 'high', 'Both are beta blockers. Taking them together can slow the heart rate too much.'],
            ['Losartan', 'Telmisartan', 'high', 'Both work the same way. Taking them together raises the risk of low blood pressure and kidney problems.'],
            ['Paracetamol', 'Paracetamol + Phenylephrine + Chlorphenamine', 'moderate', 'The cold medicine already contains paracetamol. Together you may take too much, which can harm the liver.'],
            ['Cetirizine', 'Diphenhydramine', 'moderate', 'Two antihistamines together increase drowsiness.'],
            ['Ciprofloxacin', 'Aluminum + Magnesium hydroxide', 'moderate', 'Antacids reduce how much ciprofloxacin is absorbed. Take ciprofloxacin 2 hours before or 6 hours after.'],
            ['Ciprofloxacin', 'Ferrous sulfate', 'moderate', 'Iron reduces how much ciprofloxacin is absorbed. Take ciprofloxacin 2 hours before or 6 hours after.'],
        ];
    }
}
