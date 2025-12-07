<?php
// This template is included to display a question.
// It expects a PHP variable `$initial_question` to be available.
?>
<div class="mb-6 p-4 bg-gray-50 rounded-md">
    <p class="font-medium text-gray-800 mb-2">1. <?php echo htmlspecialchars($initial_question['question']); ?></p>
    <div class="space-y-2">
        <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer">
            <input type="radio" class="form-radio text-yellow-500" name="answer" value="a" required>
            <span class="ml-3 text-gray-700">A. <?php echo htmlspecialchars($initial_question['option_a']); ?></span>
        </label>
        <!-- Repeat for options B, C, and D -->
        <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer"><input type="radio" class="form-radio text-yellow-500" name="answer" value="b" required><span class="ml-3 text-gray-700">B. <?php echo htmlspecialchars($initial_question['option_b']); ?></span></label>
        <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer"><input type="radio" class="form-radio text-yellow-500" name="answer" value="c" required><span class="ml-3 text-gray-700">C. <?php echo htmlspecialchars($initial_question['option_c']); ?></span></label>
        <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer"><input type="radio" class="form-radio text-yellow-500" name="answer" value="d" required><span class="ml-3 text-gray-700">D. <?php echo htmlspecialchars($initial_question['option_d']); ?></span></label>
    </div>
</div>