# Prompt Codes

Use this list to create the placeholders for the prompt.

## Normal questions

To insert an answer of a normal question, simply use the questions code:

```
A user is [age] years old and...
```

## Subquestions

To insert an answer of a subquestion, combine the parents question code and the subquestions code:

**Example:** You have an array question with the code `eval` where a user has to give a score to multiple items.

```
The user gave this item a score of [eval_item1] / 10.
```

## Comment questions

To insert the comment of a comment question, append 'comment' to the end of the question code:

**Example:** You have a question where a user has to choose their favorite color from a list.

```
The user chose [colorList] as their favorite color and wrote [colorList_comment] as a comment.
```

## Comment subquestions

To insert the comment of a comment subquestion, append 'comment' to the subquestion code:

```
From a list of red, green and blue a user chose the following:
    red: [colorList_red] with comment [colorList_redcomment],
    green: [colorList_green] with comment [colorList_greencomment],
    blue: [colorList_blue] with comment [colorList_bluecomment].
```

## Ranking

To get the items at a specific rank from the ranking question type:

```
The user chose [rankQuestion_1] as rank 1 and [rankQuestion_2] as rank 2 ...
```

## Array Numbers and Array Texts

These questions combine two subquestions to select the field. The structure looks as follows:

```
[qCode_sqY1_sqX1] [qCode_sqY1_sqX2]
[qCode_sqY2_sqX1] [yCode_sqY2_sqX2]
```

## Array Dual Scale

To get the answer to the subquestion for the first scale, simply use the structure `[qCode_sqCode]`.
However the answer to the second scale is stored in a completely different format. To access it use the following structure:
`[{surveyId}X{questionGroupId}X{questionId}{subquestionCode}#1]`

- surveyId is the 6 digit number of the survey
- questionGroupId and questionId can be found when clicking on the question in the structure tab of the survey
- subquestionCode is the code you'd normally use

**Example:** `[592886X51X517sq001#1]`

## Meta Fields

Every survey has a field `[startlanguage]` which stores the language the user has selected for the survey. It can also be used in a prompt.

# Answers

## Single Choice Questions

When using the code of a single choice question in the prompt the users **answer** is inserted.
That means when you define your own answers in the answers tab and the user selects one of them, the text you wrote there as the answer is inserted into the prompt.
Just use the normal question code of the parent question as a placeholder (not the answer codes).

## Array Questions

Array questions all have subquestions. Use the [**subquestions codes**](#subquestions) in the prompt to get the answer inserted.
If you use the "normal" array question type you can also define your own answer scale. Your defined answer is then inserted when using the code in the prompt.
Otherwise if you use a point choice scale, the chosen number is inserted as the answer. Same with all other scales. The plugin always inserts the chosen answer for that subquestion.

## Multiple Choice Questions

Multiple choice questions all have subquestion. Use the [**subquestions codes**](#subquestions) in the prompt to get a `Yes` or `No` inserted, depending if the user selected this item.
Unlike single choice the problem with multiple choice questions is that there is no definitive answer. This is why we only get a Yes or No for each subquestion. To use them in a prompt in a meaningful way, you'd need something like a list where you tell the LLM all the items and if the user picked them or not.

**Example:**

```
From this list of car manufacturers the user already knew:
Volkswagen: [qCode_sqCodeVW];
BMW: [qCode_sqCodeBMW];
Mercedes: [qCode_sqCodeMerc];
```

If the user chose Volkswagen and Mercedes the prompt becomes:

```
From this list of car manufacturers the user already knew:
Volkswagen: Yes;
BMW: No;
Mercedes: Yes;
```

## Text Questions

For text questions simpy use the questions code in the prompt to get the users answer inserted.
The only exception is the question type "Input on demand" where you need to specify subquestions for each entry the user can make. For this question type you need to use the subquestions codes to access the answer from the respective fields.

## Mask Questions

### Date/Time

Is inserted as a UTC string and the LLM should be able to understand it.

### Gender

Is inserted as `Female`, `Male` or `N/A`.

### Multiple Numerical Input

Same as "Input on demand". Use subquestions codes to access the number from the respective fields.

### Numerical Input

Inserts the provided number from this field into the prompt.
**Note:** The number is rounded to the nearest integer. LimeSurvey stores numbers with a large amount of unnecessary zeroes after the decimal point. So the number 3 would be stored as 3.0000000000 which sometimes lead to the LLM believing the number was way bigger than it actually was. That's why we are rounding it for the prompt.

### Ranking

Ranking questions insert your predefined answers in the prompt. See [Ranking](#ranking)

### Yes/No

Yes/No questions insert `Yes`, `No` or `No answer` into the prompt. Use the normal question code.
