#include <iostream>
#include <fstream>

#include "TestCase.h"
#include "default_config.h"


/*

  Generates a file in json format containing all of the information defined in
  config.json for easier parsing.

*/


// =====================================================================
// =====================================================================

nlohmann::json printTestCase(TestCase test) {
  nlohmann::json j;
  j["title"] = "Test " + std::to_string(test.getID()) + " " + test.getTitle();
  j["details"] = test.getDetails();
  j["points"] = test.getPoints();
  j["extra_credit"] = test.getExtraCredit();
  j["hidden"] = test.getHidden();
  j["view_testcase_message"] = test.viewTestcaseMessage();

  // THESE ELEMENTS ARE DEPRECATED / NEED TO BE REPLACED
  j["view_file_results"] = true;
  j["view_test_points"] = true;
  j["view_file"] = "";
  return j;
}



int main(int argc, char *argv[]) {

  // LOAD HW CONFIGURATION JSON
  nlohmann::json config_json = LoadAndProcessConfigJSON("");  // don't know the username yet

  nlohmann::json j;

  if (argc != 2) {
    std::cout << "USAGE: " << argv[0] << " [output_file]" << std::endl;
    return 0;
  }
  std::cout << "FILENAME " << argv[0] << std::endl;
  int total_nonec = 0;
  int total_ec = 0;

  int visible = 0;

  std::cout << "config.json.size " << config_json.size() << std::endl;
  nlohmann::json::iterator tc = config_json.find("testcases");
  
  assert (tc != config_json.end());

  int max_submissions = MAX_NUM_SUBMISSIONS;

  nlohmann::json all;
  int which_testcase = 0;

  int total_time = 0;
  //The base max time per testcase.
  int base_time = default_limits.find(RLIMIT_CPU)->second;

  int leeway = CPU_TO_WALLCLOCK_TIME_BUFFER;

  nlohmann::json::iterator rl = config_json.find("resource_limits");
  if (rl != config_json.end()){
    base_time = rl->value("RLIMIT_CPU", base_time);
  }
  std::cerr << "BASE TIME " << base_time << std::endl;

  for (typename nlohmann::json::iterator itr = tc->begin(); itr != tc->end(); itr++,which_testcase++) {
    int points = itr->value("points",0);
    bool extra_credit = itr->value("extra_credit",false);
    bool hidden = itr->value("hidden",false);

    //Add this textcases worst case time to the total worst case time.
    int cpu_time = base_time;
    nlohmann::json::iterator rl = itr->find("resource_limits");
    if (rl != itr->end()){
      cpu_time = rl->value("RLIMIT_CPU", base_time);
    }
    cpu_time += leeway;
    total_time += cpu_time;

    if (points > 0) {
      if (!extra_credit)
        total_nonec += points;
      else
        total_ec += points;
      if (!hidden)
        visible += points;
    }
    TestCase tc(config_json,which_testcase);
    if (tc.isSubmissionLimit()) {
      max_submissions = tc.getMaxSubmissions();
    }
    all.push_back(printTestCase(tc)); 
  }
  std::cout << "processed " << all.size() << " test cases" << std::endl;
  j["num_testcases"] = all.size();
  j["testcases"] = all;
  j["max_possible_grading_time"] = total_time;
  std::string start_red_text = "\033[1;31m";
  std::string end_red_text   = "\033[0m";

  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",total_nonec);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",total_ec);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);

  if (total_nonec != AUTO_POINTS) {
    std::cout << "\n" << start_red_text << "ERROR: Automated Points do not match testcases." << total_nonec 
        << "!=" << AUTO_POINTS << end_red_text << "\n" << std::endl;
    return 1;
  }
  if (total_ec != EXTRA_CREDIT_POINTS) {
    std::cout << "\n" << start_red_text << "ERROR: Extra Credit Points do not match testcases." << total_ec 
        << "!=" << EXTRA_CREDIT_POINTS << end_red_text << "\n" << std::endl;
    return 1;
  }
  if (total_nonec + TA_POINTS != TOTAL_POINTS) {
    std::cout << "\n" << start_red_text << "ERROR: Automated Points and TA Points do not match total." 
        << end_red_text << "\n" << std::endl;
    return 1;
  }


  std::string id = getAssignmentIdFromCurrentDirectory(std::string(argv[0]));
  //std::vector<std::string> part_names = PART_NAMES;

  
  j["id"] = id;
  if (config_json.find("assignment_message") != config_json.end()) {
    j["assignment_message"] = config_json.value("assignment_message",""); 
  }
  if (config_json.find("early_submission_incentive") != config_json.end()) {
    nlohmann::json early_submission_incentive = config_json.value("early_submission_incentive",nlohmann::json::object());
    nlohmann::json incentive;
    incentive["message"] = early_submission_incentive.value("message","");
    incentive["minimum_days_early"] = early_submission_incentive.value("minimum_days_early",0);
    incentive["minimum_points"] = early_submission_incentive.value("minimum_points",0);
    incentive["test_cases"] = early_submission_incentive.value("test_cases",std::vector<int>());
    j["early_submission_incentive"] = incentive; 
  }
  j["max_submissions"] = max_submissions;
  j["max_submission_size"] = config_json.value("max_submission_size",MAX_SUBMISSION_SIZE);

  j["required_capabilities"] = config_json.value("required_capabilities","default");

  nlohmann::json::iterator parts = config_json.find("part_names");
  if (parts != config_json.end()) {
    j["part_names"] =  nlohmann::json::array();
    for (int i = 0; i < parts->size(); i++) {
      j["part_names"].push_back((*parts)[i]);
    }
  }
  nlohmann::json::iterator textboxes = config_json.find("textboxes");
  if (textboxes != config_json.end()) {
    j["textboxes"] =  nlohmann::json::array();
    for (int i = 0; i < textboxes->size(); i++) {
      nlohmann::json textbox;
      nlohmann::json::iterator label = (*textboxes)[i].find("label");
      assert (label != (*textboxes)[i].end());
      assert (label->is_string());
      textbox["label"] = *label;
      // default #rows = 0 => single row, non resizeable, textbox
      textbox["rows"]  = (*textboxes)[i].value("rows",0);
      assert (int(textbox["rows"]) >= 0);
      textbox["filename"] = (*textboxes)[i].value("filename","textbox_"+std::to_string(i)+".txt");
      //list of images to display above the text box
      textbox["images"] = (*textboxes)[i].value("images", nlohmann::json::array({}));
      j["textboxes"].push_back(textbox);
    }
  }

  // By default, we have one drop zone without a part label / sub
  // directory.

  // But, if there are textboxes, but there are no explicit parts
  // (drag & drop zones / "bucket"s for file upload), set part_names
  // to an empty array (no zones for file drag & drop).
  if (parts == config_json.end() &&
      textboxes != config_json.end()) {
    j["part_names"] =  nlohmann::json::array();
  }

  j["auto_pts"] = AUTO_POINTS;
  j["points_visible"] = visible;
  j["ta_pts"] = TA_POINTS;
  j["total_pts"] = TOTAL_POINTS;
  

  // =================================================================================
  // EXPORT THE JSON FILE

  std::ofstream init;
  init.open(argv[1], std::ios::out);

  if (!init.is_open()) {
    std::cout << "\n" << start_red_text << "ERROR: unable to open new file for initialization... Now Exiting" 
        << end_red_text << "\n" << std::endl;
    return 0;
  }

  init << j.dump(4) << std::endl;

  // -----------------------------------------------------------------------
  // Also, write out the config file with automatic defaults (for debugging)
  std::string complete_config_file = argv[1];
  int b_pos = complete_config_file.find("/build/build_");
  if (b_pos != std::string::npos) {
    // only do this for the regular usage, not for the test suite
    complete_config_file = complete_config_file.substr(0,b_pos) +
      "/complete_config/complete_config_"+ complete_config_file.substr(b_pos+13,complete_config_file.size()-b_pos-13);
    std::string mkdir_command = "mkdir -p " + complete_config_file.substr(0,b_pos) + "/complete_config/";
    system (mkdir_command.c_str());
    std::ofstream complete_config;
    complete_config.open(complete_config_file, std::ios::out);
    complete_config << config_json.dump(4) << std::endl;
  }
  return 0;
}
